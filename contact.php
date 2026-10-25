<?php
/* Contact form handler for ianmusa.com.
   Receives the "Start a conversation" form and emails it to Ian through the host's mail server.
   Nothing is written to disk or a database: the message exists only as the email that is sent.
   Replies JSON: {"ok":true} or {"ok":false,"error":"..."} with a 4xx/5xx status. */

const TO        = 'ian@ianmusa.com';
const FROM      = 'ian@ianmusa.com';      // must be a mailbox on this domain or the host may reject the mail
const MAX_MSG   = 1200;
const MIN_FILL  = 3000;                   // ms: real people take longer than this to fill the form
const TOPICS    = ['Full-time role', 'Freelance project', 'Data quality audit', 'Something else'];
const TIMELINES = ['', 'As soon as possible', 'Within a month', 'In 1–3 months', 'Just exploring'];

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function reply($code, $data) { http_response_code($code); echo json_encode($data); exit; }
function field($k, $max) {
  $v = isset($_POST[$k]) ? (string) $_POST[$k] : '';
  $v = trim(str_replace("\0", '', $v));
  return function_exists('mb_substr') ? mb_substr($v, 0, $max, 'UTF-8') : substr($v, 0, $max);
}
function one_line($v) { return trim(preg_replace('/[\r\n\t]+/', ' ', $v)); } // keeps header values from being split

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Allow: POST'); reply(405, ['ok' => false, 'error' => 'method']); }

// only accept posts made from this site's own pages
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
if ($origin !== '' && !preg_match('#^https?://(www\.)?ianmusa\.com$#i', $origin)) reply(403, ['ok' => false, 'error' => 'origin']);

// spam traps: a hidden field people never see, and a form filled in faster than a person could
if (field('website', 200) !== '') reply(200, ['ok' => true]);            // pretend success so bots move on
$elapsed = (int) field('elapsed', 12);
if ($elapsed > 0 && $elapsed < MIN_FILL) reply(200, ['ok' => true]);

$name     = one_line(field('name', 120));
$email    = one_line(field('email', 200));
$org      = one_line(field('org', 160));
$timeline = one_line(field('timeline', 60));
$topic    = one_line(field('topic', 60));
$message  = field('message', MAX_MSG);

$errors = [];
if ((function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name)) < 2) $errors['name'] = 'Please enter your name.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Please enter a valid email address, for example name@company.com.';
if ((function_exists('mb_strlen') ? mb_strlen($message, 'UTF-8') : strlen($message)) < 20) $errors['message'] = 'Please add a little more detail.';
if (!in_array($topic, TOPICS, true)) $topic = 'Something else';
if (!in_array($timeline, TIMELINES, true)) $timeline = '';
if ($errors) reply(422, ['ok' => false, 'error' => 'invalid', 'fields' => $errors]);

$subject = $topic . ' enquiry from ' . $name . ($org !== '' ? ' (' . $org . ')' : '');
$lines = ['Hi Ian,', '', $message, '', '—', 'Name: ' . $name, 'Email: ' . $email];
if ($org !== '') $lines[] = 'Company: ' . $org;
if ($timeline !== '') $lines[] = 'Timeline: ' . $timeline;
$lines[] = 'Topic: ' . $topic;
$lines[] = '';
$lines[] = 'Sent from the contact form on ianmusa.com';
$body = str_replace("\n", "\r\n", str_replace(["\r\n", "\r"], "\n", implode("\n", $lines)));

$headers = [
  'From: =?UTF-8?B?' . base64_encode('ianmusa.com contact form') . '?= <' . FROM . '>',
  'Reply-To: =?UTF-8?B?' . base64_encode($name) . '?= <' . $email . '>',
  'MIME-Version: 1.0',
  'Content-Type: text/plain; charset=UTF-8',
  'Content-Transfer-Encoding: 8bit',
];
$sent = mail(TO, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers), '-f' . FROM);

if (!$sent) reply(502, ['ok' => false, 'error' => 'send']);
reply(200, ['ok' => true]);
