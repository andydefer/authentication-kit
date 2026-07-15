<?php

declare(strict_types=1);

/**
 * Configuration des variables d'environnement pour les tests.
 *
 * Copiez ce fichier en test_env.php et adaptez-le à vos besoins.
 * test_env.php est déjà dans .gitignore pour éviter de commit des secrets.
 */

return [
    // Mail
    'MAIL_FROM_ADDRESS' => 'your-email@test.com',
    'MAIL_FROM_NAME' => 'Your App Name',
    'MAIL_DEFAULT_TO' => 'recipient@test.com',

    // SMS (Twilio)
    'TWILIO_SID' => 'your_twilio_sid',
    'TWILIO_TOKEN' => 'your_twilio_token',
    'TWILIO_FROM' => '+1234567890',

    // WhatsApp (Meta)
    'WHATSAPP_ACCESS_TOKEN' => 'your_whatsapp_token',
    'WHATSAPP_PHONE_NUMBER_ID' => '123456789012345',

    // Slack
    'SLACK_WEBHOOK_URL' => 'https://hooks.slack.com/services/your/webhook/url',

    // Telegram
    'TELEGRAM_BOT_TOKEN' => 'your_bot_token',
    'TELEGRAM_CHAT_ID' => '-123456789',

    // Push (FCM/APNS)
    'FCM_API_KEY' => 'your_fcm_api_key',
    'FCM_PROJECT_ID' => 'your_project_id',
    'APNS_KEY_PATH' => '/path/to/apns/key.p8',
    'APNS_KEY_ID' => 'your_key_id',
    'APNS_TEAM_ID' => 'your_team_id',
    'APNS_BUNDLE_ID' => 'com.your.app',

    // Logs
    'NOTIFICATION_LOG_CHANNEL' => 'daily',
    'NOTIFICATION_LOG_LEVEL' => 'debug',
];
