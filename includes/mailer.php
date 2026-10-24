<?php
// ============================================================
//  includes/mailer.php
//  Builds a branded HTML email for a BMI record and sends it
//  via PHPMailer over SMTP (config in includes/config.php).
// ============================================================

require_once __DIR__ . '/../vendor/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// ============================================================
//  build_bmi_email_html()
//  Builds the HTML body for a BMI report email.
//
//  $record   : assoc array with height, weight, bmi, recorded_at
//  $person   : name of the person the reading belongs to
//  $note     : optional personal note from whoever is sending it
// ============================================================
function build_bmi_email_html(array $record, string $person, string $note = ''): string {
    $bmi      = (float) $record['bmi'];
    $cat      = bmi_category($bmi);
    $suggest  = health_suggestion($bmi);
    $date     = date('d M Y, h:i A', strtotime($record['recorded_at']));
    $height   = number_format((float) $record['height'], 1);
    $weight   = number_format((float) $record['weight'], 1);
    $bmiStr   = number_format($bmi, 1);
    $personE  = htmlspecialchars($person, ENT_QUOTES, 'UTF-8');
    $noteE    = htmlspecialchars($note, ENT_QUOTES, 'UTF-8');
    $_year    = date('Y');

    $noteBlock = '';
    if (trim($note) !== '') {
        $noteBlock = <<<HTML
        <tr>
          <td style="padding:0 32px 24px;">
            <div style="background:#0d1424;border:1px solid #26314a;border-left:3px solid #06d6a0;
                        border-radius:8px;padding:14px 18px;">
              <p style="margin:0 0 4px;font-size:11px;letter-spacing:.06em;text-transform:uppercase;
                        color:#7c8aa5;font-family:Arial,Helvetica,sans-serif;">Note</p>
              <p style="margin:0;font-size:14px;line-height:1.5;color:#e2e8f0;
                        font-family:Arial,Helvetica,sans-serif;">{$noteE}</p>
            </div>
          </td>
        </tr>
        HTML;
    }

    return <<<HTML
    <!DOCTYPE html>
    <html>
    <body style="margin:0;padding:0;background:#05070d;font-family:Arial,Helvetica,sans-serif;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#05070d;padding:32px 16px;">
        <tr>
          <td align="center">
            <table role="presentation" width="560" cellpadding="0" cellspacing="0"
                   style="max-width:560px;width:100%;background:#0f1626;border:1px solid #232d44;border-radius:16px;overflow:hidden;">

              <!-- Header -->
              <tr>
                <td style="background:linear-gradient(135deg,#06d6a0,#04c48f);padding:28px 32px;">
                  <p style="margin:0;font-size:20px;font-weight:800;color:#05070d;letter-spacing:.02em;">
                    BMI&nbsp;Monitor
                  </p>
                  <p style="margin:4px 0 0;font-size:13px;color:#05070d;opacity:.75;">
                    Automated health report
                  </p>
                </td>
              </tr>

              <!-- Intro -->
              <tr>
                <td style="padding:28px 32px 8px;">
                  <p style="margin:0;font-size:15px;color:#e2e8f0;line-height:1.6;">
                    Hello,<br><br>
                    Here is the latest BMI reading for <strong style="color:#ffffff;">{$personE}</strong>,
                    recorded on <strong style="color:#ffffff;">{$date}</strong>.
                  </p>
                </td>
              </tr>

              <!-- BMI Hero number -->
              <tr>
                <td style="padding:20px 32px 8px;" align="center">
                  <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                    <tr>
                      <td align="center" style="background:#0d1424;border:1px solid #232d44;border-radius:14px;padding:26px 20px;">
                        <p style="margin:0;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:#7c8aa5;">Body Mass Index</p>
                        <p style="margin:6px 0 10px;font-size:44px;font-weight:800;color:{$cat['color']};line-height:1;">{$bmiStr}</p>
                        <span style="display:inline-block;padding:5px 16px;border-radius:999px;font-size:12px;font-weight:700;
                                     background:{$cat['color']}22;color:{$cat['color']};border:1px solid {$cat['color']}55;">
                          {$cat['label']}
                        </span>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>

              <!-- Height / Weight -->
              <tr>
                <td style="padding:18px 32px 8px;">
                  <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                      <td width="50%" style="padding-right:6px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                               style="background:#0d1424;border:1px solid #232d44;border-radius:12px;">
                          <tr><td style="padding:16px 18px;">
                            <p style="margin:0;font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:#7c8aa5;">Height</p>
                            <p style="margin:4px 0 0;font-size:20px;font-weight:700;color:#3b82f6;">{$height} <span style="font-size:12px;color:#7c8aa5;font-weight:400;">cm</span></p>
                          </td></tr>
                        </table>
                      </td>
                      <td width="50%" style="padding-left:6px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                               style="background:#0d1424;border:1px solid #232d44;border-radius:12px;">
                          <tr><td style="padding:16px 18px;">
                            <p style="margin:0;font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:#7c8aa5;">Weight</p>
                            <p style="margin:4px 0 0;font-size:20px;font-weight:700;color:#f59e0b;">{$weight} <span style="font-size:12px;color:#7c8aa5;font-weight:400;">kg</span></p>
                          </td></tr>
                        </table>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>

              <!-- Health suggestion -->
              <tr>
                <td style="padding:18px 32px 8px;">
                  <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                         style="background:#0d1424;border:1px solid #232d44;border-left:3px solid {$cat['color']};border-radius:8px;">
                    <tr><td style="padding:16px 18px;">
                      <p style="margin:0 0 4px;font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:#7c8aa5;">Health Insight</p>
                      <p style="margin:0;font-size:13.5px;line-height:1.6;color:#c3cbdb;">{$suggest}</p>
                    </td></tr>
                  </table>
                </td>
              </tr>

              {$noteBlock}

              <!-- Footer -->
              <tr>
                <td style="padding:20px 32px 32px;">
                  <p style="margin:0;font-size:11.5px;line-height:1.6;color:#5c6a85;">
                    This is an automated report sent from BMI Monitor. BMI is a general screening
                    indicator and does not diagnose body fatness or health directly — please consult
                    a healthcare professional for personalised advice.
                  </p>
                </td>
              </tr>
            </table>
            <p style="margin:18px 0 0;font-size:11px;color:#3d475e;">&copy; {$_year} BMI Monitor</p>
          </td>
        </tr>
      </table>
    </body>
    </html>
    HTML;
}

// ============================================================
//  send_bmi_report_email()
//  Sends the BMI report email via SMTP using PHPMailer.
//  Returns ['success' => bool, 'message' => string]
// ============================================================
function send_bmi_report_email(string $toEmail, string $toName, array $record, string $person, string $note = ''): array
{
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'message' => 'That does not look like a valid email address.'
        ];
    }

    $mail = new PHPMailer(true);

    try {

        // ---------------- SMTP Configuration ----------------
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';

        // Encryption
        if (SMTP_SECURE === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        // LOCAL XAMPP FIX (Disable SSL certificate verification)
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

        // Uncomment for debugging if needed
        /*
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function ($str, $level) {
            error_log("SMTP: $str");
        };
        */

        // ---------------- Sender ----------------
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);

        // ---------------- Recipient ----------------
        $mail->addAddress($toEmail, $toName ?: $toEmail);

        // ---------------- Email Content ----------------
        $mail->isHTML(true);

        $bmi = number_format((float)$record['bmi'], 1);
        $cat = bmi_category((float)$record['bmi']);

        $mail->Subject = "BMI Report for {$person} — {$bmi} ({$cat['label']})";

        $mail->Body = build_bmi_email_html(
            $record,
            $person,
            $note
        );

        $mail->AltBody =
            "BMI Report\n\n" .
            "Name: {$person}\n" .
            "BMI: {$bmi} ({$cat['label']})\n" .
            "Height: {$record['height']} cm\n" .
            "Weight: {$record['weight']} kg\n" .
            "Recorded: {$record['recorded_at']}\n\n" .
            health_suggestion((float)$record['bmi']);

        // ---------------- Send ----------------
        $mail->send();

        return [
            'success' => true,
            'message' => "Email sent successfully to {$toEmail}."
        ];

    } catch (PHPMailerException $e) {

        return [
            'success' => false,
            'message' => "Could not send email. " . $mail->ErrorInfo
        ];

    } catch (Exception $e) {

        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}
