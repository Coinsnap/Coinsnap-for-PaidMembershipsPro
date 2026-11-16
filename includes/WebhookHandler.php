<?php
declare(strict_types=1);

namespace CoinsnapPMPro\Webhook;

defined('ABSPATH') || exit;

// Only the invoice id is taken from the request; every decision comes from the payment server's API.
class WebhookHandler
{
    // Orders still waiting for payment; "success" is PMPro's default for a new order object.
    private const OPEN = ['token', 'pending', 'review', ''];

    public function __construct(
        private string $provider,
        private string $secret,
        private array $statuses
    ) {
    }

    public function handle(string $rawBody, string $signatureHeader, int $nowSeconds): int
    {
        $problem = ($this->provider === 'btcpay')
            ? Signature::btcpayProblem($signatureHeader, $rawBody, $this->secret)
            : Signature::coinsnapProblem($signatureHeader, $rawBody, $this->secret, $nowSeconds);
        if ($problem !== null) {
            $this->logSecurity($problem);
            return 401;
        }

        $body = json_decode($rawBody, true);
        $invoiceId = (is_array($body) && is_string($body['invoiceId'] ?? null)) ? $body['invoiceId'] : '';
        if ($invoiceId === '') {
            return 200;
        }
        $claimsPaid = in_array($body['type'] ?? '', ['Settled', 'InvoiceSettled'], true);

        try {
            $invoice = $this->fetchInvoice($invoiceId);
        } catch (\Coinsnap\Exception\RequestException $e) {
            if (in_array($e->getCode(), [400, 404], true)) {
                // The dashboard's "Send test webhook" button lands here.
                $this->logInfo('unknown invoice', $invoiceId);
                return 200;
            }
            $this->logInfo('retry: API unavailable', $invoiceId);
            return 503;
        } catch (\Throwable $e) {
            $this->logInfo('retry: API unavailable', $invoiceId);
            return 503;
        }

        $metadata = is_array($invoice['metadata'] ?? null) ? $invoice['metadata'] : [];
        $orderId = ($this->provider === 'btcpay') ? ($metadata['orderId'] ?? '') : ($invoice['orderId'] ?? '');
        $id = (is_scalar($orderId) && ctype_digit((string) $orderId)) ? (int) $orderId : 0;
        $order = $id > 0 ? $this->order($id) : null;
        if ($order === null || $order['gateway'] !== 'coinsnap' || (int) $order['user_id'] <= 0) {
            $this->logInfo('not ours', $invoiceId);
            return 200;
        }

        $recorded = $order['invoices'];
        if (is_array($recorded) && $recorded !== [] && !isset($recorded[$invoiceId])) {
            $this->logSecurity('invoice not created for this order', $invoiceId);
            return 200;
        }
        // Orders placed before 1.1.0 have no record; check against what was invoiced (the subtotal).
        $expected = (is_array($recorded) && isset($recorded[$invoiceId])) ? $recorded[$invoiceId] : [
            'amount' => number_format(round((float) $order['subtotal'], 2), 2, '.', ''),
            'currency' => (string) $order['currency'],
        ];

        $decision = InvoiceDecision::decide($invoice, $this->provider === 'btcpay', (string) $expected['amount'], (string) $expected['currency']);
        if ($decision !== InvoiceDecision::PAID && ($claimsPaid || $decision === InvoiceDecision::MISMATCH)) {
            $this->logSecurity($claimsPaid ? 'forged status' : 'amount mismatch', $invoiceId);
            return 200;
        }
        if ($decision === InvoiceDecision::NONE) {
            $this->logInfo('no change', $invoiceId);
            return 200;
        }

        if (!$this->lock($id)) {
            return 503;
        }
        try {
            // Read again under the lock so a parallel webhook's change is seen.
            $this->apply($decision, $id, $this->order($id) ?? $order, $invoiceId);
        } finally {
            $this->unlock($id);
        }
        return 200;
    }

    // The membership is granted once per order; a paid or refunded order is never moved back.
    private function apply(string $decision, int $id, array $order, string $invoiceId): void
    {
        if ($order['paid_invoice'] !== '' || $order['status'] === 'refunded') {
            $this->logInfo('already processed', $invoiceId);
            return;
        }
        if ($decision === InvoiceDecision::PAID) {
            // A late payment may still settle an expired (cancelled) order.
            $this->setStatus($id, $this->statuses['settled'] ?: 'success');
            $this->grantMembership($id);
            $this->markPaid($id, $invoiceId);
            $this->logInfo('paid', $invoiceId);
            return;
        }
        $target = ($decision === InvoiceDecision::PROCESSING) ? $this->statuses['processing'] : $this->statuses['expired'];
        // Only a settled, verified invoice may mark an order paid, whatever the settings say.
        if (!in_array($order['status'], self::OPEN, true) || $target === '' || $target === 'success' || $target === $order['status']) {
            $this->logInfo('no change', $invoiceId);
            return;
        }
        $this->setStatus($id, $target);
        $this->logInfo('status ' . $decision, $invoiceId);
    }

    protected function fetchInvoice(string $invoiceId): array
    {
        return (new \Coinsnap\Client\Invoice(\PMProGateway_coinsnap::getApiUrl(), (string) \PMProGateway_coinsnap::getApiKey()))
            ->getInvoice((string) \PMProGateway_coinsnap::getStoreId(), $invoiceId)->getData();
    }

    protected function order(int $id): ?array
    {
        $order = new \MemberOrder();
        $order->getMemberOrderByID($id);
        if (empty($order->id)) {
            return null;
        }
        return [
            'status' => (string) $order->status,
            'gateway' => (string) $order->gateway,
            'user_id' => (int) $order->user_id,
            'subtotal' => (string) $order->subtotal,
            'currency' => (string) get_option('pmpro_currency', 'USD'),
            'invoices' => get_pmpro_membership_order_meta($id, 'coinsnap_invoices', true),
            'paid_invoice' => (string) get_pmpro_membership_order_meta($id, 'coinsnap_paid_invoice', true),
        ];
    }

    protected function setStatus(int $id, string $status): void
    {
        $order = new \MemberOrder();
        $order->getMemberOrderByID($id);
        $order->status = $status;
        $order->saveOrder();
    }

    protected function markPaid(int $id, string $invoiceId): void
    {
        update_pmpro_membership_order_meta($id, 'coinsnap_paid_invoice', $invoiceId);
    }

    protected function grantMembership(int $id): void
    {
        $morder = new \MemberOrder();
        $morder->getMemberOrderByID($id);
        $morder->getMembershipLevel();
        $morder->getDiscountCode();
        $discount_code_id = '';
        if (!empty($morder->discount_code)) {
            $morder->getMembershipLevel(true);
            $discount_code_id = $morder->discount_code->id;
        }

        $morder->membership_level = apply_filters('pmpro_inshandler_level', $morder->membership_level, $morder->user_id);
        $startdate = apply_filters('pmpro_checkout_start_date', "'" . current_time('mysql') . "'", $morder->user_id, $morder->membership_level);
        $enddate = 'NULL';
        if (!empty($morder->membership_level->expiration_number)) {
            $enddate = "'" . date_i18n('Y-m-d', strtotime('+ ' . $morder->membership_level->expiration_number . ' ' . $morder->membership_level->expiration_period, current_time('timestamp'))) . "'";
        }
        $enddate = apply_filters('pmpro_checkout_end_date', $enddate, $morder->user_id, $morder->membership_level, $startdate);

        pmpro_changeMembershipLevel([
            'user_id' => $morder->user_id,
            'membership_id' => $morder->membership_level->id,
            'code_id' => $discount_code_id,
            'initial_payment' => $morder->membership_level->initial_payment,
            'billing_amount' => $morder->membership_level->billing_amount,
            'cycle_number' => $morder->membership_level->cycle_number,
            'cycle_period' => $morder->membership_level->cycle_period,
            'billing_limit' => $morder->membership_level->billing_limit,
            'trial_amount' => $morder->membership_level->trial_amount,
            'trial_limit' => $morder->membership_level->trial_limit,
            'startdate' => $startdate,
            'enddate' => $enddate,
        ], $morder->user_id);
    }

    protected function lock(int $id): bool
    {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 10)', 'coinsnap_pmpro_' . $id)) === 1;
    }

    protected function unlock(int $id): void
    {
        global $wpdb;
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', 'coinsnap_pmpro_' . $id));
    }

    protected function logInfo(string $reason, string $invoiceId = ''): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(self::line('INFO', $reason, $invoiceId));
        }
    }

    // At most 10 security lines a minute, so a flood of fake requests can't fill the log.
    protected function logSecurity(string $reason, string $invoiceId = ''): void
    {
        $key = 'coinsnappmpro_seclog_' . gmdate('YmdHi');
        $count = (int) get_transient($key) + 1;
        set_transient($key, $count, 120);
        if ($count === 11) {
            error_log('Coinsnap SECURITY webhook: more rejections this minute, not logged');
        }
        if ($count <= 10) {
            error_log(self::line('SECURITY', $reason, $invoiceId));
        }
    }

    public static function line(string $level, string $reason, string $invoiceId): string
    {
        // The invoice id comes from the request.
        $id = substr((string) preg_replace('/[^A-Za-z0-9_-]/', '', $invoiceId), 0, 64);
        return sprintf('Coinsnap %s webhook reason=%s invoice=%s', $level, $reason, $id !== '' ? $id : '-');
    }
}
