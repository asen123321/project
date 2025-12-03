<?php

namespace App\MessageHandler;

use App\Entity\Booking;
use App\Message\AdminBookingNotification;
use App\Repository\BookingRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsMessageHandler]
class AdminBookingNotificationHandler
{
    public function __construct(
        private BookingRepository $bookingRepository,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private UriSigner $uriSigner,
        private LoggerInterface $logger,
        private string $adminEmail
    ) {
    }

    public function __invoke(AdminBookingNotification $message): void
    {
        $booking = $this->bookingRepository->find($message->getBookingId());

        if (!$booking) {
            $this->logger->error('Booking not found for admin notification', [
                'booking_id' => $message->getBookingId()
            ]);
            return;
        }

        $this->logger->info('Sending admin notification for new booking', [
            'booking_id' => $booking->getId(),
            'client_name' => $booking->getClientName(),
            'service' => $booking->getService()->getName(),
            'appointment_date' => $booking->getAppointmentDate()->format('Y-m-d H:i:s')
        ]);

        // Generate unsigned URLs
        $approveUrl = $this->urlGenerator->generate(
            'booking_quick_approve',
            ['id' => $booking->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $rejectUrl = $this->urlGenerator->generate(
            'booking_quick_reject',
            ['id' => $booking->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        // Sign the URLs for security
        $signedApproveUrl = $this->uriSigner->sign($approveUrl);
        $signedRejectUrl = $this->uriSigner->sign($rejectUrl);

        $this->logger->info('Generated signed URLs for admin actions', [
            'booking_id' => $booking->getId(),
            'approve_url_length' => strlen($signedApproveUrl),
            'reject_url_length' => strlen($signedRejectUrl)
        ]);

        // Compose email with action buttons
        $email = (new Email())
            ->from('noreply@hairsalon.com')
            ->to($this->adminEmail)
            ->subject('🔔 New Booking Request - ' . $booking->getService()->getName())
            ->html($this->buildEmailBody($booking, $signedApproveUrl, $signedRejectUrl));

        try {
            $this->mailer->send($email);

            $this->logger->info('Admin notification email sent successfully', [
                'booking_id' => $booking->getId(),
                'admin_email' => $this->adminEmail
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send admin notification email', [
                'booking_id' => $booking->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    private function buildEmailBody(Booking $booking, string $approveUrl, string $rejectUrl): string
    {
        $appointmentDate = $booking->getAppointmentDate();
        $service = $booking->getService();
        $stylist = $booking->getStylist();
        $clientPhone = $booking->getClientPhone() ?: 'Not provided';

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 10px 10px 0 0;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            background: #f9f9f9;
            padding: 30px;
            border-left: 1px solid #ddd;
            border-right: 1px solid #ddd;
        }
        .booking-details {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-weight: bold;
            color: #667eea;
        }
        .detail-value {
            color: #333;
        }
        .action-buttons {
            text-align: center;
            padding: 20px 0;
        }
        .btn {
            display: inline-block;
            padding: 15px 40px;
            margin: 10px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 16px;
            transition: transform 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .btn-approve {
            background: #10b981;
            color: white;
        }
        .btn-reject {
            background: #ef4444;
            color: white;
        }
        .footer {
            background: #f9f9f9;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border: 1px solid #ddd;
            border-radius: 0 0 10px 10px;
        }
        .notes-section {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔔 New Booking Request</h1>
        <p style="margin: 10px 0 0 0; opacity: 0.9;">Action required - Please approve or reject</p>
    </div>

    <div class="content">
        <div class="booking-details">
            <div class="detail-row">
                <span class="detail-label">📅 Appointment Date:</span>
                <span class="detail-value">{$appointmentDate->format('l, F j, Y')}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">🕐 Time:</span>
                <span class="detail-value">{$appointmentDate->format('g:i A')}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">💇 Service:</span>
                <span class="detail-value">{$service->getName()}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">⏱️ Duration:</span>
                <span class="detail-value">{$service->getDurationMinutes()} minutes</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">💰 Price:</span>
                <span class="detail-value">\${$service->getPrice()}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">✂️ Stylist:</span>
                <span class="detail-value">{$stylist->getName()}</span>
            </div>
        </div>

        <div class="booking-details">
            <h3 style="margin-top: 0; color: #667eea;">👤 Client Information</h3>
            <div class="detail-row">
                <span class="detail-label">Name:</span>
                <span class="detail-value">{$booking->getClientName()}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Email:</span>
                <span class="detail-value">{$booking->getClientEmail()}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Phone:</span>
                <span class="detail-value">{$clientPhone}</span>
            </div>
        </div>

        {$this->renderNotesSection($booking)}

        <div class="action-buttons">
            <h3 style="color: #333; margin-bottom: 20px;">Take Action:</h3>
            <a href="{$approveUrl}" class="btn btn-approve">✓ Approve Booking</a>
            <a href="{$rejectUrl}" class="btn btn-reject">✗ Reject Booking</a>
        </div>

        <p style="text-align: center; color: #666; font-size: 14px; margin-top: 30px;">
            Click a button above to instantly update the booking status.<br>
            The client will be automatically notified of your decision.
        </p>
    </div>

    <div class="footer">
        <p><strong>Hair Salon Booking System</strong></p>
        <p>This is an automated notification. These action links are secure and expire after use.</p>
    </div>
</body>
</html>
HTML;
    }

    private function renderNotesSection(Booking $booking): string
    {
        $notes = $booking->getNotes();

        if (!$notes || trim($notes) === '') {
            return '';
        }

        $escapedNotes = htmlspecialchars($notes, ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <div class="notes-section">
            <strong style="color: #856404;">📝 Client Notes:</strong><br>
            <div style="margin-top: 10px; color: #333;">{$escapedNotes}</div>
        </div>
HTML;
    }
}
