<?php

namespace App\Enums;

enum NotificationSlug: string
{
    case CourseCompleted = 'course_completed';
    case CertificateAvailable = 'certificate_available';
    case LessonUpdated = 'lesson_updated';
    case CertificateRecalculationRequired = 'certificate_recalculation_required';

    case AccountPasswordChanged = 'account_password_changed';
    case AccountEmailChanged = 'account_email_changed';

    case PaymentApproved = 'payment_approved';
    case PaymentFailed = 'payment_failed';
    case PaymentRefunded = 'payment_refunded';
    case OrderCanceled = 'order_canceled';

    case SubscriptionActivated = 'subscription_activated';
    case SubscriptionRenewed = 'subscription_renewed';
    case SubscriptionPastDue = 'subscription_past_due';
    case SubscriptionCanceled = 'subscription_canceled';

    case CertificateUpdated = 'certificate_updated';
    case CertificateRevoked = 'certificate_revoked';

    case Newsletter = 'newsletter';
    case LessonReminder = 'lesson_reminder';

    /**
     * Column on users that lets the user opt out of this e-mail.
     * Null means transactional: always delivered.
     */
    public function preferenceKey(): ?string
    {
        return match ($this) {
            self::Newsletter => 'notify_newsletter',
            self::LessonReminder => 'notify_lesson_reminders',
            default => null,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function title(): string
    {
        return match ($this) {
            self::CourseCompleted => 'Curso concluído',
            self::CertificateAvailable => 'Certificado disponível',
            self::LessonUpdated => 'Aula atualizada',
            self::CertificateRecalculationRequired => 'Certificado precisa ser recalculado',
            self::AccountPasswordChanged => 'Sua senha foi alterada',
            self::AccountEmailChanged => 'Seu e-mail foi alterado',
            self::PaymentApproved => 'Pagamento aprovado',
            self::PaymentFailed => 'Falha no pagamento',
            self::PaymentRefunded => 'Pagamento estornado',
            self::OrderCanceled => 'Pedido cancelado',
            self::SubscriptionActivated => 'Assinatura ativada',
            self::SubscriptionRenewed => 'Assinatura renovada',
            self::SubscriptionPastDue => 'Assinatura em atraso',
            self::SubscriptionCanceled => 'Assinatura cancelada',
            self::CertificateUpdated => 'Certificado atualizado',
            self::CertificateRevoked => 'Certificado revogado',
            self::Newsletter => 'Novidades',
            self::LessonReminder => 'Lembrete de aula',
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::AccountPasswordChanged, self::AccountEmailChanged => 'security',
            self::PaymentApproved, self::PaymentFailed, self::PaymentRefunded, self::OrderCanceled => 'payment',
            self::SubscriptionActivated, self::SubscriptionRenewed,
            self::SubscriptionPastDue, self::SubscriptionCanceled => 'subscription',
            self::Newsletter, self::LessonReminder => 'engagement',
            default => 'certificate',
        };
    }

    public function severity(): string
    {
        return match ($this) {
            self::PaymentApproved, self::SubscriptionActivated, self::SubscriptionRenewed,
            self::CertificateAvailable, self::CertificateUpdated, self::CourseCompleted => 'success',
            self::PaymentFailed, self::SubscriptionPastDue, self::CertificateRecalculationRequired,
            self::CertificateRevoked, self::AccountPasswordChanged, self::AccountEmailChanged => 'warning',
            self::PaymentRefunded, self::OrderCanceled, self::SubscriptionCanceled => 'danger',
            default => 'info',
        };
    }
}
