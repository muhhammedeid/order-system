<?php

namespace Tests\Unit\WhatsApp;

use App\Support\WhatsApp\SessionState;
use App\Support\WhatsApp\SessionView;
use Tests\TestCase;

class SessionViewTest extends TestCase
{
    public function test_working_maps_to_connected_with_full_actions(): void
    {
        $view = SessionView::make($this->sessionWith('WORKING'), true, true);

        $this->assertSame(SessionView::STATE_CONNECTED, $view->state);
        $this->assertSame('WORKING', $view->rawStatus);
        $this->assertSame('success', $view->color());
        $this->assertTrue($view->isConnected());
        $this->assertFalse($view->isQrVisible());
        $this->assertTrue($view->allows(SessionView::ACTION_REFRESH));
        $this->assertTrue($view->allows(SessionView::ACTION_RESTART));
        $this->assertTrue($view->allows(SessionView::ACTION_STOP));
        $this->assertTrue($view->allows(SessionView::ACTION_LOGOUT));
        $this->assertFalse($view->allows(SessionView::ACTION_START));
    }

    public function test_connecting_only_allows_refresh(): void
    {
        $view = SessionView::make($this->sessionWith('STARTING'), true, true);

        $this->assertSame(SessionView::STATE_CONNECTING, $view->state);
        $this->assertTrue($view->allows(SessionView::ACTION_REFRESH));
        $this->assertFalse($view->allows(SessionView::ACTION_STOP));
        $this->assertFalse($view->allows(SessionView::ACTION_START));
        $this->assertFalse($view->allows(SessionView::ACTION_LOGOUT));
    }

    public function test_qr_required_shows_qr_and_allows_refresh_qr_and_stop(): void
    {
        $view = SessionView::make($this->sessionWith('SCAN_QR_CODE'), true, true);

        $this->assertSame(SessionView::STATE_QR_REQUIRED, $view->state);
        $this->assertTrue($view->isQrVisible());
        $this->assertSame('warning', $view->color());
        $this->assertTrue($view->allows(SessionView::ACTION_REFRESH_QR));
        $this->assertTrue($view->allows(SessionView::ACTION_STOP));
        $this->assertFalse($view->allows(SessionView::ACTION_RESTART));
    }

    public function test_stopped_allows_start_and_refresh(): void
    {
        $view = SessionView::make($this->sessionWith('STOPPED'), true, true);

        $this->assertSame(SessionView::STATE_STOPPED, $view->state);
        $this->assertTrue($view->allows(SessionView::ACTION_START));
        $this->assertTrue($view->allows(SessionView::ACTION_REFRESH));
        $this->assertFalse($view->allows(SessionView::ACTION_STOP));
        $this->assertFalse($view->allows(SessionView::ACTION_LOGOUT));
    }

    public function test_failed_maps_to_attention_with_recovery_actions(): void
    {
        $view = SessionView::make($this->sessionWith('FAILED'), true, true);

        $this->assertSame(SessionView::STATE_ATTENTION, $view->state);
        $this->assertSame('danger', $view->color());
        $this->assertTrue($view->allows(SessionView::ACTION_REFRESH));
        $this->assertTrue($view->allows(SessionView::ACTION_RESTART));
        $this->assertTrue($view->allows(SessionView::ACTION_LOGOUT));
        $this->assertFalse($view->allows(SessionView::ACTION_START));
    }

    public function test_passkey_states_map_to_verification_required(): void
    {
        foreach (['PASSKEY_REQUIRED', 'PASSKEY_CONFIRMATION_REQUIRED'] as $raw) {
            $view = SessionView::make($this->sessionWith($raw), true, true);

            $this->assertSame(SessionView::STATE_VERIFICATION_REQUIRED, $view->state);
            $this->assertSame($raw, $view->rawStatus);
            $this->assertTrue($view->allows(SessionView::ACTION_RESTART));
        }
    }

    public function test_unknown_status_falls_back_to_attention_and_keeps_raw_status(): void
    {
        $view = SessionView::make($this->sessionWith('SOMETHING_NEW'), true, true);

        $this->assertSame(SessionView::STATE_ATTENTION, $view->state);
        $this->assertSame('SOMETHING_NEW', $view->rawStatus);
    }

    public function test_missing_session_maps_to_not_created(): void
    {
        $view = SessionView::make(null, true, true);

        $this->assertSame(SessionView::STATE_NOT_CREATED, $view->state);
        $this->assertNull($view->rawStatus);
        $this->assertTrue($view->allows(SessionView::ACTION_CREATE_START));
    }

    public function test_disabled_integration_wins_over_every_other_state(): void
    {
        $view = SessionView::make($this->sessionWith('WORKING'), false, true);

        $this->assertSame(SessionView::STATE_DISABLED, $view->state);
        $this->assertNull($view->rawStatus);
        $this->assertFalse($view->allows(SessionView::ACTION_START));
        $this->assertTrue($view->allows(SessionView::ACTION_REFRESH));
    }

    public function test_unhealthy_service_maps_to_service_unavailable(): void
    {
        $view = SessionView::make($this->sessionWith('WORKING'), true, false);

        $this->assertSame(SessionView::STATE_SERVICE_UNAVAILABLE, $view->state);
        $this->assertSame('WORKING', $view->rawStatus);
        $this->assertFalse($view->allows(SessionView::ACTION_LOGOUT));
    }

    public function test_identity_phone_requires_a_regular_c_us_id(): void
    {
        $this->assertSame('201234567890', SessionView::identityPhone(['id' => '201234567890@c.us']));
        $this->assertNull(SessionView::identityPhone(['id' => '214457011683409@lid']));
        $this->assertNull(SessionView::identityPhone(['id' => 'status@broadcast']));
        $this->assertNull(SessionView::identityPhone(null));
        $this->assertNull(SessionView::identityPhone([]));
    }

    public function test_identity_name_uses_push_name_when_present(): void
    {
        $this->assertSame('MAI', SessionView::identityName(['pushName' => 'MAI']));
        $this->assertSame('MAI', SessionView::identityName(['pushName' => '  MAI  ']));
        $this->assertNull(SessionView::identityName(['pushName' => '   ']));
        $this->assertNull(SessionView::identityName(null));
        $this->assertNull(SessionView::identityName([]));
    }

    public function test_every_admin_state_has_a_label_and_guidance_translation(): void
    {
        $states = [
            SessionView::STATE_CONNECTED,
            SessionView::STATE_CONNECTING,
            SessionView::STATE_QR_REQUIRED,
            SessionView::STATE_STOPPED,
            SessionView::STATE_ATTENTION,
            SessionView::STATE_VERIFICATION_REQUIRED,
            SessionView::STATE_NOT_CREATED,
            SessionView::STATE_DISABLED,
            SessionView::STATE_SERVICE_UNAVAILABLE,
        ];

        foreach ($states as $state) {
            $this->assertNotSame(
                'admin.whatsapp.states.'.$state,
                __('admin.whatsapp.states.'.$state),
                "Missing label translation for state {$state}",
            );
            $this->assertNotSame(
                'admin.whatsapp.guidance.'.$state,
                __('admin.whatsapp.guidance.'.$state),
                "Missing guidance translation for state {$state}",
            );
        }
    }

    private function sessionWith(string $status): SessionState
    {
        return new SessionState(name: 'default', status: $status);
    }
}
