<?php

if (!defined('ABSPATH')) {
    exit;
}

class EfbAddonPhrases {

    private static $instance = null;

    private static $addon_providers = [];

    private static $phrase_cache = [];

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->register_default_addons();
    }

    private function register_default_addons() {

        self::register_addon('telegram', [__CLASS__, 'get_telegram_phrases']);

        self::register_addon('Autofill', [__CLASS__, 'get_autofill_phrases']);

        self::register_addon('payment', [__CLASS__, 'get_payment_phrases']);

        self::register_addon('sms', [__CLASS__, 'get_sms_phrases']);

        self::register_addon('googlesheet', [__CLASS__, 'get_googlesheet_phrases']);

        self::register_addon('humanshield', [__CLASS__, 'get_humanshield_phrases']);

    }

    public static function register_addon($addon_key, $callback) {
        self::$addon_providers[$addon_key] = $callback;
    }

    public static function get_addon_phrases($addon_key, $ac = null, $state = false) {

        $cache_key = $addon_key . '_' . ($state ? '1' : '0');
        if (isset(self::$phrase_cache[$cache_key])) {
            return self::$phrase_cache[$cache_key];
        }

        if (!isset(self::$addon_providers[$addon_key])) {
            return [];
        }

        $phrases = call_user_func(self::$addon_providers[$addon_key], $ac, $state);

        self::$phrase_cache[$cache_key] = $phrases;

        return $phrases;
    }

    public static function get_multiple_addon_phrases($addon_keys, $ac = null, $state = false) {
        $phrases = [];
        foreach ($addon_keys as $key) {
            $phrases = array_merge($phrases, self::get_addon_phrases($key, $ac, $state));
        }
        return $phrases;
    }

    public static function clear_cache() {
        self::$phrase_cache = [];
    }

    public static function get_registered_addons() {
        return array_keys(self::$addon_providers);
    }

    public static function get_telegram_phrases($ac = null, $state = false) {
        return [

            /* translators: Settings = configuration tab title */

            /* translators: Test Message = test tab title */
            "test_message" => $state && isset($ac->text->test_message) ? $ac->text->test_message : esc_html__('Test Message', 'flx-forms'),

            /* translators: Activity Log = activity tab title */
            "activity" => $state && isset($ac->text->activity) ? $ac->text->activity : esc_html__('Activity Log', 'flx-forms'),

            /* translators: Telegram Settings = main settings header */
            "telegram_settings" => $state && isset($ac->text->telegram_settings) ? $ac->text->telegram_settings : esc_html__('Telegram Settings', 'flx-forms'),

            /* translators: Telegram Integration = header title */
            "telegram_title" => $state && isset($ac->text->telegram_title) ? $ac->text->telegram_title : esc_html__('Telegram Integration', 'flx-forms'),

            /* translators: Real-time Form Notifications = header subtitle */
            "telegram_subtitle" => $state && isset($ac->text->telegram_subtitle) ? $ac->text->telegram_subtitle : esc_html__('Real-time Form Notifications', 'flx-forms'),

            /* translators: Enabled = status when feature is active */
            "enabled" => $state && isset($ac->text->enabled) ? $ac->text->enabled : esc_html__('Enabled', 'flx-forms'),

            /* translators: Status = current state label */
            "status" => $state && isset($ac->text->status) ? $ac->text->status : esc_html__('Status', 'flx-forms'),

            /* translators: Notifications are active = status description */
            "notifications_active" => $state && isset($ac->text->notifications_active) ? $ac->text->notifications_active : esc_html__('Notifications are active', 'flx-forms'),

            /* translators: Configure settings to enable = status description */
            "configure_to_enable" => $state && isset($ac->text->configure_to_enable) ? $ac->text->configure_to_enable : esc_html__('Configure settings to enable', 'flx-forms'),

            /* translators: Enable Telegram Notifications = toggle label */
            "enable_telegram" => $state && isset($ac->text->enable_telegram) ? $ac->text->enable_telegram : esc_html__('Enable Telegram Notifications', 'flx-forms'),

            /* translators: Bot Token = authentication token field label */
            "bot_token" => $state && isset($ac->text->bot_token) ? $ac->text->bot_token : esc_html__('Bot Token', 'flx-forms'),

            /* translators: Enter your bot token = placeholder text */
            "enter_bot_token" => $state && isset($ac->text->enter_bot_token) ? $ac->text->enter_bot_token : esc_html__('Enter your bot token', 'flx-forms'),

            /* translators: Get your bot token from @BotFather on Telegram = help text */
            "bot_token_help" => $state && isset($ac->text->bot_token_help) ? $ac->text->bot_token_help : esc_html__('Get your bot token from @BotFather on Telegram', 'flx-forms'),

            /* translators: Chat ID = chat identifier field label */
            "chat_id" => $state && isset($ac->text->chat_id) ? $ac->text->chat_id : esc_html__('Chat ID', 'flx-forms'),

            /* translators: Enter chat ID = placeholder text */
            "enter_chat_id" => $state && isset($ac->text->enter_chat_id) ? $ac->text->enter_chat_id : esc_html__('Enter chat ID', 'flx-forms'),

            /* translators: Use @username for channels or numeric ID for private chats = help text */
            "chat_id_help" => $state && isset($ac->text->chat_id_help) ? $ac->text->chat_id_help : esc_html__('Use @username for channels or numeric ID for private chats', 'flx-forms'),

            /* translators: Test = button text */
            "test" => $state && isset($ac->text->test) ? $ac->text->test : esc_html__('Test', 'flx-forms'),

            /* translators: Save Settings = save button text */
            "save_settings" => $state && isset($ac->text->save_settings) ? $ac->text->save_settings : esc_html__('Save Settings', 'flx-forms'),

            /* translators: Refresh = refresh button text */
            "refresh" => $state && isset($ac->text->refresh) ? $ac->text->refresh : esc_html__('Refresh', 'flx-forms'),

            /* translators: Send Test Message = test button text */
            "send_test_message" => $state && isset($ac->text->send_test_message) ? $ac->text->send_test_message : esc_html__('Send Test Message', 'flx-forms'),

            /* translators: Quick Tips = tips section title */
            "quick_tips" => $state && isset($ac->text->quick_tips) ? $ac->text->quick_tips : esc_html__('Quick Tips', 'flx-forms'),

            /* translators: Tip 1 = first tip, contains %s placeholder for @BotFather link */
            "tip_1" => $state && isset($ac->text->tip_1) ? $ac->text->tip_1 : esc_html__('Create a bot using %s', 'flx-forms'),

            /* translators: Tip 2 = second tip */
            "tip_2" => $state && isset($ac->text->tip_2) ? $ac->text->tip_2 : esc_html__('Add your bot to the target chat', 'flx-forms'),

            /* translators: Tip 3 = third tip */
            "tip_3" => $state && isset($ac->text->tip_3) ? $ac->text->tip_3 : esc_html__('Use the test feature to verify setup', 'flx-forms'),

            /* translators: Tip 4 = fourth tip */
            "tip_4" => $state && isset($ac->text->tip_4) ? $ac->text->tip_4 : esc_html__('Monitor activity for troubleshooting', 'flx-forms'),

            /* translators: Tip 5 = fifth tip, contains %s placeholder for Chat ID finder link */
            "tip_5" => $state && isset($ac->text->tip_5) ? $ac->text->tip_5 : esc_html__('Find your Chat ID using %s', 'flx-forms'),

            /* translators: @BotFather = clickable link text (not translatable) */
            "botfather_link" => '@BotFather',

            /* translators: @WSYourIDBot = clickable link text (not translatable) */
            "chatid_finder_link" => '@WSYourIDBot',

            /* translators: Enter your test message here = placeholder */
            "enter_test_message" => $state && isset($ac->text->enter_test_message) ? $ac->text->enter_test_message : esc_html__('Enter your test message here...', 'flx-forms'),

            /* translators: Default test message content */
            "default_test_message" => $state && isset($ac->text->default_test_message) ? $ac->text->default_test_message : esc_html__('Test Message

This is a test message from Flux Forms.

If you receive this message, your Telegram integration is working correctly!', 'flx-forms'),

            /* translators: Test Information = info section title */
            "test_info" => $state && isset($ac->text->test_info) ? $ac->text->test_info : esc_html__('Test Information', 'flx-forms'),

            /* translators: Test description text */
            "test_description" => $state && isset($ac->text->test_description) ? $ac->text->test_description : esc_html__('Use this tab to send test messages and verify your Telegram bot configuration.', 'flx-forms'),

            /* translators: Test warning message */
            "test_warning" => $state && isset($ac->text->test_warning) ? $ac->text->test_warning : esc_html__('Make sure to save your settings before testing!', 'flx-forms'),

            /* translators: Activity Log = tab title */
            "activity_log" => $state && isset($ac->text->activity_log) ? $ac->text->activity_log : esc_html__('Activity Log', 'flx-forms'),

            /* translators: Date = column header */
            "date" => $state && isset($ac->text->date) ? $ac->text->date : esc_html__('Date', 'flx-forms'),

            /* translators: No activity found = empty state message */
            "no_activity" => $state && isset($ac->text->no_activity) ? $ac->text->no_activity : esc_html__('No activity found', 'flx-forms'),

            /* translators: Setting Up Your Bot = help section title */
            "setup_bot" => $state && isset($ac->text->setup_bot) ? $ac->text->setup_bot : esc_html__('Setting Up Your Bot', 'flx-forms'),

            /* translators: Step 1: Create a Bot */
            "step_1" => $state && isset($ac->text->step_1) ? $ac->text->step_1 : esc_html__('Create a Bot', 'flx-forms'),

            /* translators: Step 1 description */
            "step_1_desc" => $state && isset($ac->text->step_1_desc) ? $ac->text->step_1_desc : esc_html__('Message @BotFather on Telegram and use /newbot command', 'flx-forms'),

            /* translators: Step 2: Get Bot Token */
            "step_2" => $state && isset($ac->text->step_2) ? $ac->text->step_2 : esc_html__('Get Bot Token', 'flx-forms'),

            /* translators: Step 2 description */
            "step_2_desc" => $state && isset($ac->text->step_2_desc) ? $ac->text->step_2_desc : esc_html__('Copy the bot token provided by BotFather', 'flx-forms'),

            /* translators: Step 3: Get Chat ID */
            "step_3" => $state && isset($ac->text->step_3) ? $ac->text->step_3 : esc_html__('Get Chat ID', 'flx-forms'),

            /* translators: Step 3 description */
            "step_3_desc" => $state && isset($ac->text->step_3_desc) ? $ac->text->step_3_desc : esc_html__('For groups: Add bot and use @username. For private: Use numeric ID', 'flx-forms'),

            /* translators: Step 4: Test Connection */
            "step_4" => $state && isset($ac->text->step_4) ? $ac->text->step_4 : esc_html__('Test Connection', 'flx-forms'),

            /* translators: Step 4 description */
            "step_4_desc" => $state && isset($ac->text->step_4_desc) ? $ac->text->step_4_desc : esc_html__('Use the test feature to verify everything works', 'flx-forms'),

            /* translators: Getting Chat ID = help section title */
            "getting_chat_id" => $state && isset($ac->text->getting_chat_id) ? $ac->text->getting_chat_id : esc_html__('Getting Chat ID', 'flx-forms'),

            /* translators: For Groups/Channels = subsection title */
            "for_groups" => $state && isset($ac->text->for_groups) ? $ac->text->for_groups : esc_html__('For Groups/Channels:', 'flx-forms'),

            /* translators: Group step 1 */
            "group_step_1" => $state && isset($ac->text->group_step_1) ? $ac->text->group_step_1 : esc_html__('Add your bot to the group', 'flx-forms'),

            /* translators: Group step 2 */
            "group_step_2" => $state && isset($ac->text->group_step_2) ? $ac->text->group_step_2 : esc_html__('Use @your_bot_username', 'flx-forms'),

            /* translators: Group step 3 */
            "group_step_3" => $state && isset($ac->text->group_step_3) ? $ac->text->group_step_3 : esc_html__('For channels, use @channel_username', 'flx-forms'),

            /* translators: For Private Chats = subsection title */
            "for_private" => $state && isset($ac->text->for_private) ? $ac->text->for_private : esc_html__('For Private Chats:', 'flx-forms'),

            /* translators: Private step 1 */
            "private_step_1" => $state && isset($ac->text->private_step_1) ? $ac->text->private_step_1 : esc_html__('Message @userinfobot', 'flx-forms'),

            /* translators: Private step 2 */
            "private_step_2" => $state && isset($ac->text->private_step_2) ? $ac->text->private_step_2 : esc_html__('Copy your numeric user ID', 'flx-forms'),

            /* translators: Private step 3 */
            "private_step_3" => $state && isset($ac->text->private_step_3) ? $ac->text->private_step_3 : esc_html__('Use the numeric ID in settings', 'flx-forms'),

            /* translators: Important! = warning label */
            "important" => $state && isset($ac->text->important) ? $ac->text->important : esc_html__('Important!', 'flx-forms'),

            /* translators: Help note text */
            "help_note" => $state && isset($ac->text->help_note) ? $ac->text->help_note : esc_html__('Make sure your bot has permission to send messages to the target chat.', 'flx-forms'),

            /* translators: Confirm reset dialog */
            "confirm_reset" => $state && isset($ac->text->confirm_reset) ? $ac->text->confirm_reset : esc_html__('Are you sure you want to reset all settings?', 'flx-forms'),

            /* translators: Confirm clear logs dialog */
            "confirm_clear" => $state && isset($ac->text->confirm_clear) ? $ac->text->confirm_clear : esc_html__('Are you sure you want to clear all activity logs?', 'flx-forms'),

            /* translators: An error occurred = generic error */
            "error_occurred" => $state && isset($ac->text->error_occurred) ? $ac->text->error_occurred : esc_html__('An error occurred', 'flx-forms'),

            /* translators: Please fill in all required fields = validation error */
            "fill_required_fields" => $state && isset($ac->text->fill_required_fields) ? $ac->text->fill_required_fields : esc_html__('Please fill in all required fields', 'flx-forms'),

            /* translators: Connection test failed = test error */
            "connection_failed" => $state && isset($ac->text->connection_failed) ? $ac->text->connection_failed : esc_html__('Connection test failed', 'flx-forms'),

            /* translators: Please fill in all fields = validation error */
            "fill_all_fields" => $state && isset($ac->text->fill_all_fields) ? $ac->text->fill_all_fields : esc_html__('Please fill in all fields', 'flx-forms'),

            /* translators: Failed to send message = send error */
            "send_failed" => $state && isset($ac->text->send_failed) ? $ac->text->send_failed : esc_html__('Failed to send message', 'flx-forms'),

            /* translators: Failed to load activity = load error */
            "load_failed" => $state && isset($ac->text->load_failed) ? $ac->text->load_failed : esc_html__('Failed to load activity', 'flx-forms'),

            /* translators: Bot token is empty = error when bot token is not provided */
            "botTokenEmpty" => $state && isset($ac->text->botTokenEmpty) ? $ac->text->botTokenEmpty : esc_html__('Bot token is empty', 'flx-forms'),

            /* translators: Invalid token = error for invalid bot token */
            "invalidToken" => $state && isset($ac->text->invalidToken) ? $ac->text->invalidToken : esc_html__('Invalid token', 'flx-forms'),

            /* translators: Unknown error = generic error message */
            "unknownError" => $state && isset($ac->text->unknownError) ? $ac->text->unknownError : esc_html__('Unknown error', 'flx-forms'),

            /* translators: Error connecting to Telegram server = connection error */
            "telegramConnectionError" => $state && isset($ac->text->telegramConnectionError) ? $ac->text->telegramConnectionError : esc_html__('Error connecting to Telegram server', 'flx-forms'),

            /* translators: Telegram configuration error = config error prefix */
            "telegramConfigError" => $state && isset($ac->text->telegramConfigError) ? $ac->text->telegramConfigError : esc_html__('Telegram configuration error', 'flx-forms'),

            /* translators: Message sent = success message */
            "messageSent" => $state && isset($ac->text->messageSent) ? $ac->text->messageSent : esc_html__('Message sent', 'flx-forms'),

            /* translators: Telegram bot is not configured = error when bot token is not set */
            "telegramBotNotConfigured" => $state && isset($ac->text->telegramBotNotConfigured) ? $ac->text->telegramBotNotConfigured : esc_html__('Telegram bot is not configured', 'flx-forms'),

            /* translators: Invalid response from Telegram server = API error */
            "invalidTelegramResponse" => $state && isset($ac->text->invalidTelegramResponse) ? $ac->text->invalidTelegramResponse : esc_html__('Invalid response from Telegram server', 'flx-forms'),

            /* translators: Telegram user not found for this phone number = user not found error */
            "telegramUserNotFound" => $state && isset($ac->text->telegramUserNotFound) ? $ac->text->telegramUserNotFound : esc_html__('Telegram user not found for this phone number', 'flx-forms'),

            /* translators: Telegram user account is not verified = user not verified error */
            "telegramUserNotVerified" => $state && isset($ac->text->telegramUserNotVerified) ? $ac->text->telegramUserNotVerified : esc_html__('Telegram user account is not verified', 'flx-forms'),

            /* translators: Error occurred while sending Telegram message = send error */
            "telegramSendError" => $state && isset($ac->text->telegramSendError) ? $ac->text->telegramSendError : esc_html__('Error occurred while sending Telegram message', 'flx-forms'),

            /* translators: Invalid security nonce = security error */
            "invalidSecurityNonce" => $state && isset($ac->text->invalidSecurityNonce) ? $ac->text->invalidSecurityNonce : esc_html__('Invalid security nonce', 'flx-forms'),

            /* translators: Invalid form ID = form ID error */
            "invalidFormId" => $state && isset($ac->text->invalidFormId) ? $ac->text->invalidFormId : esc_html__('Invalid form ID', 'flx-forms'),

            /* translators: Telegram notifications activated successfully = success message */
            "telegramActivationSuccess" => $state && isset($ac->text->telegramActivationSuccess) ? $ac->text->telegramActivationSuccess : esc_html__('Telegram notifications activated successfully', 'flx-forms'),
        ];
    }

    public static function get_sms_phrases($ac = null, $state = false) {
        return [

            /* translators: SMS Send & Manage Messages = subtitle in SMS header */
            "smsSendMessages" => $state && isset($ac->text->smsSendMessages) ? $ac->text->smsSendMessages : esc_html__('Send & Manage Messages', 'flx-forms'),

            /* translators: SMS intro description = description text under SMS header */
            "smsIntroDesc" => $state && isset($ac->text->smsIntroDesc) ? $ac->text->smsIntroDesc : esc_html__('Send SMS messages directly from your dashboard and track all sent messages.', 'flx-forms'),

            /* translators: Quick Send = feature badge label */
            "quickSend" => $state && isset($ac->text->quickSend) ? $ac->text->quickSend : esc_html__('Quick Send', 'flx-forms'),

            /* translators: Message History = tab title and feature badge */
            "messageHistory" => $state && isset($ac->text->messageHistory) ? $ac->text->messageHistory : esc_html__('Message History', 'flx-forms'),

            /* translators: Total Sent = label for total sent messages counter */
            "totalSent" => $state && isset($ac->text->totalSent) ? $ac->text->totalSent : esc_html__('Total Sent', 'flx-forms'),

            /* translators: Messages sent from your dashboard = hint under status card */
            "smsStatusHint" => $state && isset($ac->text->smsStatusHint) ? $ac->text->smsStatusHint : esc_html__('Messages sent from your dashboard', 'flx-forms'),

            /* translators: Send Message = tab title for composing an SMS */
            "sendMessage" => $state && isset($ac->text->sendMessage) ? $ac->text->sendMessage : esc_html__('Send Message', 'flx-forms'),

            /* translators: Compose and send an SMS message = subtitle on send card */
            "sendSmsDesc" => $state && isset($ac->text->sendSmsDesc) ? $ac->text->sendSmsDesc : esc_html__('Compose and send an SMS message', 'flx-forms'),

            /* translators: Type your message here... = textarea placeholder */
            "typeYourMessage" => $state && isset($ac->text->typeYourMessage) ? $ac->text->typeYourMessage : esc_html__('Type your message here...', 'flx-forms'),

            /* translators: Phone Number = label for phone input field */
            "phoneNumber" => $state && isset($ac->text->phoneNumber) ? $ac->text->phoneNumber : esc_html__('Phone Number', 'flx-forms'),

            /* translators: SMS message field hint text */
            "smsMessageHint" => $state && isset($ac->text->smsMessageHint) ? $ac->text->smsMessageHint : esc_html__('Write the message you want to send via SMS', 'flx-forms'),

            /* translators: SMS phone field hint text */
            "smsPhoneHint" => $state && isset($ac->text->smsPhoneHint) ? $ac->text->smsPhoneHint : esc_html__('Enter phone number with country code (e.g. +11234567890)', 'flx-forms'),

            /* translators: Quick Tips = title for tips sidebar */
            "quickTips" => $state && isset($ac->text->quickTips) ? $ac->text->quickTips : esc_html__('Quick Tips', 'flx-forms'),

            /* translators: SMS tip about international phone format */
            "smsTip1" => $state && isset($ac->text->smsTip1) ? $ac->text->smsTip1 : esc_html__('Use international format: +[country code][number]', 'flx-forms'),

            /* translators: SMS tip about provider configuration */
            "smsTip2" => $state && isset($ac->text->smsTip2) ? $ac->text->smsTip2 : esc_html__('Messages are sent via your configured SMS provider', 'flx-forms'),

            /* translators: SMS tip about message history */
            "smsTip3" => $state && isset($ac->text->smsTip3) ? $ac->text->smsTip3 : esc_html__('All sent messages are logged in the History tab', 'flx-forms'),

            /* translators: No messages sent yet = empty state message */
            "noRecordsFound" => $state && isset($ac->text->noRecordsFound) ? $ac->text->noRecordsFound : esc_html__('No messages sent yet', 'flx-forms'),

            /* translators: sent = status label for sent messages */
            "sent" => $state && isset($ac->text->sent) ? $ac->text->sent : esc_html__('sent', 'flx-forms'),

            /* translators: Learn how to use the SMS feature = help subtitle */
            "smsHelpDesc" => $state && isset($ac->text->smsHelpDesc) ? $ac->text->smsHelpDesc : esc_html__('Learn how to use the SMS feature', 'flx-forms'),






            /* translators: Important Notes = help section title */
            "smsHelpNotes" => $state && isset($ac->text->smsHelpNotes) ? $ac->text->smsHelpNotes : esc_html__('Important Notes', 'flx-forms'),

            /* translators: Note about country code requirement */
            "smsHelpNote1" => $state && isset($ac->text->smsHelpNote1) ? $ac->text->smsHelpNote1 : esc_html__('Phone numbers must include country code (e.g. +1 for US, +44 for UK)', 'flx-forms'),

            /* translators: Note about SMS provider requirement */
            "smsHelpNote2" => $state && isset($ac->text->smsHelpNote2) ? $ac->text->smsHelpNote2 : esc_html__('An SMS provider plugin must be installed and configured', 'flx-forms'),

            /* translators: Note about form notification settings */
            "smsHelpNote3" => $state && isset($ac->text->smsHelpNote3) ? $ac->text->smsHelpNote3 : esc_html__('SMS notifications for forms can be set up in each form\'s settings', 'flx-forms'),

            /* translators: Note about message history tab */
            "smsHelpNote4" => $state && isset($ac->text->smsHelpNote4) ? $ac->text->smsHelpNote4 : esc_html__('All sent messages are recorded in the Message History tab', 'flx-forms'),

            /* translators: Supported SMS Providers = help section title */
            "smsHelpProviders" => $state && isset($ac->text->smsHelpProviders) ? $ac->text->smsHelpProviders : esc_html__('Supported SMS Providers', 'flx-forms'),
        ];
    }

    public static function get_stripe_phrases($ac = null, $state = false) {
        return [

        ];
    }

    /**
     * Messages for the Human Shield add-on (Form Security & Spam Protection):
     * both the visitor-facing REST responses (hs*) and the admin settings panel
     * it renders via human-shield-admin-efb.js (hs* fields below the "Admin
     * panel" marker). All live here so every string can be edited/translated
     * from the Phrases page. Values are used as plain text and escaped by the
     * consumer, so keep them unescaped (__ not esc_html__).
     */
    public static function get_humanshield_phrases($ac = null, $state = false) {
        return [

            /* translators: Shown when the security add-on is switched off */
            "hsDisabled" => $state && isset($ac->text->hsDisabled) ? $ac->text->hsDisabled : __('Form Security & Spam Protection is disabled.', 'flx-forms'),

            /* translators: Shown when the server cannot run the security checks but the form still works */
            "hsServiceNotAvailable" => $state && isset($ac->text->hsServiceNotAvailable) ? $ac->text->hsServiceNotAvailable : __('The security service is not available right now. Your form still works.', 'flx-forms'),

            /* translators: Ask the visitor to reload the form and submit again */
            "hsRefreshForm" => $state && isset($ac->text->hsRefreshForm) ? $ac->text->hsRefreshForm : __('Please refresh the form and try again.', 'flx-forms'),

            /* translators: Rate-limit message shown when too many requests arrive */
            "hsTooManyRequests" => $state && isset($ac->text->hsTooManyRequests) ? $ac->text->hsTooManyRequests : __('Too many requests. Please try again shortly.', 'flx-forms'),

            /* translators: The browser did not send a security challenge id */
            "hsChallengeMissing" => $state && isset($ac->text->hsChallengeMissing) ? $ac->text->hsChallengeMissing : __('Missing challenge.', 'flx-forms'),

            /* translators: The security challenge is no longer valid */
            "hsChallengeExpired" => $state && isset($ac->text->hsChallengeExpired) ? $ac->text->hsChallengeExpired : __('Expired challenge.', 'flx-forms'),

            /* translators: The security challenge was already used once */
            "hsChallengeUsed" => $state && isset($ac->text->hsChallengeUsed) ? $ac->text->hsChallengeUsed : __('Challenge already used.', 'flx-forms'),

            /* translators: The challenge does not belong to the submitted form */
            "hsFormMismatch" => $state && isset($ac->text->hsFormMismatch) ? $ac->text->hsFormMismatch : __('Form mismatch.', 'flx-forms'),

            /* translators: Temporary storage/service failure inside the security add-on */
            "hsServiceTempUnavailable" => $state && isset($ac->text->hsServiceTempUnavailable) ? $ac->text->hsServiceTempUnavailable : __('The security service is temporarily unavailable.', 'flx-forms'),

            /* translators: The signed security token expired or could not be verified */
            "hsFormExpired" => $state && isset($ac->text->hsFormExpired) ? $ac->text->hsFormExpired : __('The form was open for too long or could not be verified. Please refresh the page and submit again.', 'flx-forms'),

            /* translators: Generic block message when a request looks automated */
            "hsLooksUnusual" => $state && isset($ac->text->hsLooksUnusual) ? $ac->text->hsLooksUnusual : __('Your request looked too fast or unusual. Please try again in a few minutes.', 'flx-forms'),

            /* translators: Softer quarantine message asking the visitor to wait and retry */
            "hsQuarantine" => $state && isset($ac->text->hsQuarantine) ? $ac->text->hsQuarantine : __('Your request looked unusual. Please wait a moment and try again.', 'flx-forms'),

            /* translators: Shown when the security service is temporarily unavailable (fail-closed) */
            "hsServiceUnavailable" => $state && isset($ac->text->hsServiceUnavailable) ? $ac->text->hsServiceUnavailable : __('The security service is temporarily unavailable. Please try again later.', 'flx-forms'),

            /* ===== Admin panel (Security & Spam Protection settings page) =====
             * Rendered by human-shield-admin-efb.js via its own esc() helper, so
             * keep these unescaped (__ not esc_html__), same as the messages above. */

            /* translators: Form Security & Spam Protection = add-on name shown as the panel header title */
            "hsTitle" => $state && isset($ac->text->hsTitle) ? $ac->text->hsTitle : __('Form Security & Spam Protection', 'flx-forms'),
            /* translators: Panel header subtitle summarizing what the add-on does */
            "hsSubtitle" => $state && isset($ac->text->hsSubtitle) ? $ac->text->hsSubtitle : __('Behavior-based anti-spam and cost protection', 'flx-forms'),
            /* translators: Overview = first tab in the admin panel */
            "hsOverview" => $state && isset($ac->text->hsOverview) ? $ac->text->hsOverview : __('Overview', 'flx-forms'),
            /* translators: Protection = tab with request-protection thresholds */
            "hsProtection" => $state && isset($ac->text->hsProtection) ? $ac->text->hsProtection : __('Protection', 'flx-forms'),
            /* translators: Paid Limits = tab with stop-loss limits for paid notifications */
            "hsPaidLimits" => $state && isset($ac->text->hsPaidLimits) ? $ac->text->hsPaidLimits : __('Paid Limits', 'flx-forms'),
            /* translators: Logs = tab showing recent security decisions */
            "hsLogs" => $state && isset($ac->text->hsLogs) ? $ac->text->hsLogs : __('Logs', 'flx-forms'),
            /* translators: System = tab showing PHP/server requirements */
            "hsSystem" => $state && isset($ac->text->hsSystem) ? $ac->text->hsSystem : __('System', 'flx-forms'),
            /* translators: Save Settings = button label */
            "hsSave" => $state && isset($ac->text->hsSave) ? $ac->text->hsSave : __('Save Settings', 'flx-forms'),
            /* translators: Status shown on the Save button while the request is in flight */
            "hsSaving" => $state && isset($ac->text->hsSaving) ? $ac->text->hsSaving : __('Saving…', 'flx-forms'),
            /* translators: Success message after settings are saved */
            "hsSaved" => $state && isset($ac->text->hsSaved) ? $ac->text->hsSaved : __('Settings saved.', 'flx-forms'),
            /* translators: Generic error message for a failed admin-ajax request */
            "hsFailed" => $state && isset($ac->text->hsFailed) ? $ac->text->hsFailed : __('Request failed.', 'flx-forms'),
            /* translators: Enabled = generic toggle field label */
            "hsEnabled" => $state && isset($ac->text->hsEnabled) ? $ac->text->hsEnabled : __('Enabled', 'flx-forms'),
            /* translators: Mode = field label for the protection mode selector */
            "hsMode" => $state && isset($ac->text->hsMode) ? $ac->text->hsMode : __('Mode', 'flx-forms'),
            /* translators: Mode option - logs decisions but never blocks requests */
            "hsMonitor" => $state && isset($ac->text->hsMonitor) ? $ac->text->hsMonitor : __('Monitor only', 'flx-forms'),
            /* translators: Mode option - blocks the worst requests, quarantines borderline ones */
            "hsSoftBlock" => $state && isset($ac->text->hsSoftBlock) ? $ac->text->hsSoftBlock : __('Soft block', 'flx-forms'),
            /* translators: Mode option - the strictest enforcement level */
            "hsStrict" => $state && isset($ac->text->hsStrict) ? $ac->text->hsStrict : __('Strict', 'flx-forms'),
            /* translators: Clear Logs = button that deletes the stored security log entries */
            "hsClearLogs" => $state && isset($ac->text->hsClearLogs) ? $ac->text->hsClearLogs : __('Clear Logs', 'flx-forms'),
            /* translators: Fallback message shown if wp_localize_script data failed to load */
            "hsConfigMissing" => $state && isset($ac->text->hsConfigMissing) ? $ac->text->hsConfigMissing : __('The Security & Spam Protection admin data could not be loaded. Another plugin may be blocking the script settings, or the page was cached. Reload the page; if it persists, disable admin script optimization for this page.', 'flx-forms'),
            /* translators: Fallback message shown if the admin panel JavaScript throws an error while rendering */
            "hsRenderFailed" => $state && isset($ac->text->hsRenderFailed) ? $ac->text->hsRenderFailed : __('The Security & Spam Protection panel failed to render. Reload the page or check the browser console.', 'flx-forms'),
            /* translators: %s = comma-separated list of disabled PHP function names. Banner shown when required PHP functions are missing */
            "hsMissingRequired" => $state && isset($ac->text->hsMissingRequired) ? $ac->text->hsMissingRequired : __('Protection is paused: required PHP functions are disabled on this server: %s. Ask your host to remove them from the disable_functions line in php.ini. Your forms keep working without protection until then.', 'flx-forms'),
            /* translators: Banner shown when the add-on's database tables could not be created */
            "hsMissingTables" => $state && isset($ac->text->hsMissingTables) ? $ac->text->hsMissingTables : __('Protection is paused: the add-on database tables could not be created. Check that the database user can CREATE tables, then reload this page.', 'flx-forms'),
            /* translators: Banner shown when the WordPress REST API is unavailable on this site */
            "hsMissingRestApi" => $state && isset($ac->text->hsMissingRestApi) ? $ac->text->hsMissingRestApi : __('Protection is paused: the WordPress REST API is not available on this site.', 'flx-forms'),

            /* translators: Header badge - the add-on scores request behavior */
            "hsBadgeBehaviorScoring" => $state && isset($ac->text->hsBadgeBehaviorScoring) ? $ac->text->hsBadgeBehaviorScoring : __('Behavior scoring', 'flx-forms'),
            /* translators: Header badge - the add-on enforces rate limits */
            "hsBadgeRateLimits" => $state && isset($ac->text->hsBadgeRateLimits) ? $ac->text->hsBadgeRateLimits : __('Rate limits', 'flx-forms'),
            /* translators: Header badge - the add-on protects against paid-notification cost overruns */
            "hsBadgeCostGuard" => $state && isset($ac->text->hsBadgeCostGuard) ? $ac->text->hsBadgeCostGuard : __('Cost guard', 'flx-forms'),

            /* translators: Status pill label shown after enabling protection but before saving */
            "hsStatusReadyEnable" => $state && isset($ac->text->hsStatusReadyEnable) ? $ac->text->hsStatusReadyEnable : __('Ready to enable', 'flx-forms'),
            /* translators: Status pill hint shown after enabling protection but before saving */
            "hsStatusReadyEnableHint" => $state && isset($ac->text->hsStatusReadyEnableHint) ? $ac->text->hsStatusReadyEnableHint : __('Save settings to start protection.', 'flx-forms'),
            /* translators: Status pill label shown after disabling protection but before saving */
            "hsStatusReadyDisable" => $state && isset($ac->text->hsStatusReadyDisable) ? $ac->text->hsStatusReadyDisable : __('Ready to disable', 'flx-forms'),
            /* translators: Status pill hint shown after disabling protection but before saving */
            "hsStatusReadyDisableHint" => $state && isset($ac->text->hsStatusReadyDisableHint) ? $ac->text->hsStatusReadyDisableHint : __('Save settings to pause protection.', 'flx-forms'),
            /* translators: Status pill label shown when protection is turned off */
            "hsStatusDisabled" => $state && isset($ac->text->hsStatusDisabled) ? $ac->text->hsStatusDisabled : __('Disabled', 'flx-forms'),
            /* translators: Status pill hint shown when protection is turned off */
            "hsStatusDisabledHint" => $state && isset($ac->text->hsStatusDisabledHint) ? $ac->text->hsStatusDisabledHint : __('Protection is paused.', 'flx-forms'),
            /* translators: Status pill label shown when server requirements are not met */
            "hsStatusNeedsAttention" => $state && isset($ac->text->hsStatusNeedsAttention) ? $ac->text->hsStatusNeedsAttention : __('Needs attention', 'flx-forms'),
            /* translators: Status pill hint shown when server requirements are not met */
            "hsStatusNeedsAttentionHint" => $state && isset($ac->text->hsStatusNeedsAttentionHint) ? $ac->text->hsStatusNeedsAttentionHint : __('Fix system requirements before protection can run.', 'flx-forms'),
            /* translators: Status pill label shown when protection is running normally */
            "hsStatusActive" => $state && isset($ac->text->hsStatusActive) ? $ac->text->hsStatusActive : __('Active', 'flx-forms'),
            /* translators: Status pill hint shown when protection is running normally */
            "hsStatusActiveHint" => $state && isset($ac->text->hsStatusActiveHint) ? $ac->text->hsStatusActiveHint : __('Behavior checks and rate limits are running.', 'flx-forms'),

            /* translators: Overview tab stat card - count of allowed requests */
            "hsStatAllowed" => $state && isset($ac->text->hsStatAllowed) ? $ac->text->hsStatAllowed : __('Allowed', 'flx-forms'),
            /* translators: Overview tab stat card - count of blocked requests */
            "hsStatBlocked" => $state && isset($ac->text->hsStatBlocked) ? $ac->text->hsStatBlocked : __('Blocked', 'flx-forms'),
            /* translators: Overview tab stat card - count of quarantined (soft-rejected) requests */
            "hsStatQuarantined" => $state && isset($ac->text->hsStatQuarantined) ? $ac->text->hsStatQuarantined : __('Quarantined', 'flx-forms'),
            /* translators: Overview tab stat card - count of paid notifications suppressed by the cost guard */
            "hsStatSuppressed" => $state && isset($ac->text->hsStatSuppressed) ? $ac->text->hsStatSuppressed : __('Cost Suppressed', 'flx-forms'),
            /* translators: Overview tab card title summarizing current protection settings */
            "hsProtectionSummaryTitle" => $state && isset($ac->text->hsProtectionSummaryTitle) ? $ac->text->hsProtectionSummaryTitle : __('Protection summary', 'flx-forms'),
            /* translators: Overview tab card subtitle */
            "hsProtectionSummaryDesc" => $state && isset($ac->text->hsProtectionSummaryDesc) ? $ac->text->hsProtectionSummaryDesc : __('Current operating mode and the most important thresholds.', 'flx-forms'),
            /* translators: Overview summary row label - minimum score required to accept a submission */
            "hsSummarySubmitScore" => $state && isset($ac->text->hsSummarySubmitScore) ? $ac->text->hsSummarySubmitScore : __('Submit score', 'flx-forms'),
            /* translators: Overview summary row label - minimum score required to send a paid notification */
            "hsSummaryPaidScore" => $state && isset($ac->text->hsSummaryPaidScore) ? $ac->text->hsSummaryPaidScore : __('Paid notification score', 'flx-forms'),
            /* translators: Overview summary row label - lifetime of the human-verification token */
            "hsSummaryTokenTtl" => $state && isset($ac->text->hsSummaryTokenTtl) ? $ac->text->hsSummaryTokenTtl : __('Token TTL', 'flx-forms'),
            /* translators: Overview summary row label - submit rate limit per IP per minute */
            "hsSummaryIpSubmitMin" => $state && isset($ac->text->hsSummaryIpSubmitMin) ? $ac->text->hsSummaryIpSubmitMin : __('IP submit/min', 'flx-forms'),
            /* translators: Overview summary row label - response/reply rate limit per IP per minute */
            "hsSummaryResponseAddMin" => $state && isset($ac->text->hsSummaryResponseAddMin) ? $ac->text->hsSummaryResponseAddMin : __('Response add/min', 'flx-forms'),

            /* translators: Protection tab card title */
            "hsRequestProtectionTitle" => $state && isset($ac->text->hsRequestProtectionTitle) ? $ac->text->hsRequestProtectionTitle : __('Request protection', 'flx-forms'),
            /* translators: Protection tab card subtitle */
            "hsRequestProtectionDesc" => $state && isset($ac->text->hsRequestProtectionDesc) ? $ac->text->hsRequestProtectionDesc : __('Controls that run before EFB REST callbacks.', 'flx-forms'),
            /* translators: Toggle label that turns the whole add-on on or off */
            "hsFieldEnableLabel" => $state && isset($ac->text->hsFieldEnableLabel) ? $ac->text->hsFieldEnableLabel : __('Enable Human Shield', 'flx-forms'),
            /* translators: Hint under the master enable toggle */
            "hsFieldEnableHint" => $state && isset($ac->text->hsFieldEnableHint) ? $ac->text->hsFieldEnableHint : __('Activate behavior tokens and rate limits.', 'flx-forms'),
            /* translators: Hint under the protection Mode selector */
            "hsFieldModeHint" => $state && isset($ac->text->hsFieldModeHint) ? $ac->text->hsFieldModeHint : __('Monitor logs only; soft block is recommended for production rollout.', 'flx-forms'),
            /* translators: Field label - minimum behavior score required to accept a submission */
            "hsFieldMinScoreSubmitLabel" => $state && isset($ac->text->hsFieldMinScoreSubmitLabel) ? $ac->text->hsFieldMinScoreSubmitLabel : __('Minimum submit score', 'flx-forms'),
            /* translators: Hint for the minimum submit score field */
            "hsFieldMinScoreSubmitHint" => $state && isset($ac->text->hsFieldMinScoreSubmitHint) ? $ac->text->hsFieldMinScoreSubmitHint : __('Requests below this score are suspicious.', 'flx-forms'),
            /* translators: Field label - score threshold below which a request is hard-blocked */
            "hsFieldBlockScoreLabel" => $state && isset($ac->text->hsFieldBlockScoreLabel) ? $ac->text->hsFieldBlockScoreLabel : __('Block score below', 'flx-forms'),
            /* translators: Hint for the hard-block score threshold field */
            "hsFieldBlockScoreHint" => $state && isset($ac->text->hsFieldBlockScoreHint) ? $ac->text->hsFieldBlockScoreHint : __('Hard block threshold.', 'flx-forms'),
            /* translators: Field label - score threshold below which a request is soft-rejected */
            "hsFieldQuarantineScoreLabel" => $state && isset($ac->text->hsFieldQuarantineScoreLabel) ? $ac->text->hsFieldQuarantineScoreLabel : __('Quarantine score below', 'flx-forms'),
            /* translators: Hint for the soft-rejection score threshold field */
            "hsFieldQuarantineScoreHint" => $state && isset($ac->text->hsFieldQuarantineScoreHint) ? $ac->text->hsFieldQuarantineScoreHint : __('Soft rejection threshold.', 'flx-forms'),
            /* translators: Field label - minimum seconds a visitor must spend before submitting */
            "hsFieldMinFillTimeLabel" => $state && isset($ac->text->hsFieldMinFillTimeLabel) ? $ac->text->hsFieldMinFillTimeLabel : __('Minimum fill time', 'flx-forms'),
            /* translators: Hint for the minimum fill time field */
            "hsFieldMinFillTimeHint" => $state && isset($ac->text->hsFieldMinFillTimeHint) ? $ac->text->hsFieldMinFillTimeHint : __('Base minimum seconds before submit.', 'flx-forms'),
            /* translators: Field label - lifetime in seconds of the single-use human token */
            "hsFieldTokenTtlLabel" => $state && isset($ac->text->hsFieldTokenTtlLabel) ? $ac->text->hsFieldTokenTtlLabel : __('Token TTL seconds', 'flx-forms'),
            /* translators: Hint for the token TTL field */
            "hsFieldTokenTtlHint" => $state && isset($ac->text->hsFieldTokenTtlHint) ? $ac->text->hsFieldTokenTtlHint : __('Short-lived single-use human token.', 'flx-forms'),
            /* translators: Field label - lifetime in seconds of a browser challenge */
            "hsFieldChallengeTtlLabel" => $state && isset($ac->text->hsFieldChallengeTtlLabel) ? $ac->text->hsFieldChallengeTtlLabel : __('Challenge TTL seconds', 'flx-forms'),
            /* translators: Hint for the challenge TTL field */
            "hsFieldChallengeTtlHint" => $state && isset($ac->text->hsFieldChallengeTtlHint) ? $ac->text->hsFieldChallengeTtlHint : __('How long a browser challenge can stay open.', 'flx-forms'),
            /* translators: Field label - overall API rate limit per IP per minute */
            "hsFieldApiIpMinLabel" => $state && isset($ac->text->hsFieldApiIpMinLabel) ? $ac->text->hsFieldApiIpMinLabel : __('All API requests per IP/min', 'flx-forms'),
            /* translators: Hint for the overall API rate limit field */
            "hsFieldApiIpMinHint" => $state && isset($ac->text->hsFieldApiIpMinHint) ? $ac->text->hsFieldApiIpMinHint : __('Global application-layer backstop.', 'flx-forms'),
            /* translators: Field label - submit rate limit per IP per minute */
            "hsFieldSubmitIpMinLabel" => $state && isset($ac->text->hsFieldSubmitIpMinLabel) ? $ac->text->hsFieldSubmitIpMinLabel : __('Submits per IP/min', 'flx-forms'),
            /* translators: Hint for the per-IP submit rate limit (per minute) field */
            "hsFieldSubmitIpMinHint" => $state && isset($ac->text->hsFieldSubmitIpMinHint) ? $ac->text->hsFieldSubmitIpMinHint : __('Per-IP submit limit.', 'flx-forms'),
            /* translators: Field label - submit rate limit per IP per hour */
            "hsFieldSubmitIpHourLabel" => $state && isset($ac->text->hsFieldSubmitIpHourLabel) ? $ac->text->hsFieldSubmitIpHourLabel : __('Submits per IP/hour', 'flx-forms'),
            /* translators: Hint for the per-IP submit rate limit (per hour) field */
            "hsFieldSubmitIpHourHint" => $state && isset($ac->text->hsFieldSubmitIpHourHint) ? $ac->text->hsFieldSubmitIpHourHint : __('Longer per-IP submit limit.', 'flx-forms'),
            /* translators: Field label - submit rate limit shared by all visitors to one form */
            "hsFieldFormGlobalMinLabel" => $state && isset($ac->text->hsFieldFormGlobalMinLabel) ? $ac->text->hsFieldFormGlobalMinLabel : __('Form global/min', 'flx-forms'),
            /* translators: Hint for the form-wide submit rate limit field */
            "hsFieldFormGlobalMinHint" => $state && isset($ac->text->hsFieldFormGlobalMinHint) ? $ac->text->hsFieldFormGlobalMinHint : __('Total submit pressure per form.', 'flx-forms'),
            /* translators: Field label - rate limit for confirmation-code / tracking lookups per IP per minute */
            "hsFieldResponseGetIpMinLabel" => $state && isset($ac->text->hsFieldResponseGetIpMinLabel) ? $ac->text->hsFieldResponseGetIpMinLabel : __('Tracking lookups per IP/min', 'flx-forms'),
            /* translators: Hint for the tracking-lookup rate limit field */
            "hsFieldResponseGetIpMinHint" => $state && isset($ac->text->hsFieldResponseGetIpMinHint) ? $ac->text->hsFieldResponseGetIpMinHint : __('Protects confirmation-code lookup.', 'flx-forms'),
            /* translators: Field label - rate limit for public reply submissions per IP per minute */
            "hsFieldResponseAddIpMinLabel" => $state && isset($ac->text->hsFieldResponseAddIpMinLabel) ? $ac->text->hsFieldResponseAddIpMinLabel : __('Responses per IP/min', 'flx-forms'),
            /* translators: Hint for the public reply-box rate limit field */
            "hsFieldResponseAddIpMinHint" => $state && isset($ac->text->hsFieldResponseAddIpMinHint) ? $ac->text->hsFieldResponseAddIpMinHint : __('Protects public reply box.', 'flx-forms'),
            /* translators: Field label - rate limit for replies to a single tracked conversation, per hour */
            "hsFieldReplyTrackHourLabel" => $state && isset($ac->text->hsFieldReplyTrackHourLabel) ? $ac->text->hsFieldReplyTrackHourLabel : __('Replies per tracking code/hour', 'flx-forms'),
            /* translators: Hint for the per-tracking-code reply rate limit field; "0" refers to the field's own value */
            "hsFieldReplyTrackHourHint" => $state && isset($ac->text->hsFieldReplyTrackHourHint) ? $ac->text->hsFieldReplyTrackHourHint : __('Caps replies to one tracked conversation (across IPs). 0 disables.', 'flx-forms'),
            /* translators: Field label - file upload rate limit per IP per minute */
            "hsFieldUploadIpMinLabel" => $state && isset($ac->text->hsFieldUploadIpMinLabel) ? $ac->text->hsFieldUploadIpMinLabel : __('Uploads per IP/min', 'flx-forms'),
            /* translators: Hint for the file upload rate limit field */
            "hsFieldUploadIpMinHint" => $state && isset($ac->text->hsFieldUploadIpMinHint) ? $ac->text->hsFieldUploadIpMinHint : __('Protects upload endpoint.', 'flx-forms'),
            /* translators: Field label - payment-start rate limit per IP per minute */
            "hsFieldPaymentIpMinLabel" => $state && isset($ac->text->hsFieldPaymentIpMinLabel) ? $ac->text->hsFieldPaymentIpMinLabel : __('Payment starts per IP/min', 'flx-forms'),
            /* translators: Hint for the payment-start rate limit field */
            "hsFieldPaymentIpMinHint" => $state && isset($ac->text->hsFieldPaymentIpMinHint) ? $ac->text->hsFieldPaymentIpMinHint : __('Protects payment REST routes.', 'flx-forms'),
            /* translators: Section title for the settings that control how many files one visitor may upload */
            "hsUploadBudgetTitle" => $state && isset($ac->text->hsUploadBudgetTitle) ? $ac->text->hsUploadBudgetTitle : __('File upload budget', 'flx-forms'),
            /* translators: Description of the upload budget section. Explains that the plugin already limits uploads by itself and these boxes only override it. */
            "hsUploadBudgetDesc" => $state && isset($ac->text->hsUploadBudgetDesc) ? $ac->text->hsUploadBudgetDesc : __('Flux Forms already limits uploads on its own: each visitor may upload three files per file field on the form, and the response box allows three. Leave a box at 0 to keep that automatic behaviour, or enter a number to set it yourself.', 'flx-forms'),
            /* translators: Field label - how many uploads are allowed for each file field on a form */
            "hsFieldUploadRetryLabel" => $state && isset($ac->text->hsFieldUploadRetryLabel) ? $ac->text->hsFieldUploadRetryLabel : __('Uploads allowed per file field', 'flx-forms'),
            /* translators: Hint for the per-file-field upload allowance; "0" refers to the field's own value */
            "hsFieldUploadRetryHint" => $state && isset($ac->text->hsFieldUploadRetryHint) ? $ac->text->hsFieldUploadRetryHint : __('Default 3, so a two-field form allows six uploads. 0 keeps the default.', 'flx-forms'),
            /* translators: Field label - a fixed total number of uploads allowed per visitor */
            "hsFieldUploadQuotaLabel" => $state && isset($ac->text->hsFieldUploadQuotaLabel) ? $ac->text->hsFieldUploadQuotaLabel : __('Total uploads per visitor', 'flx-forms'),
            /* translators: Hint for the fixed total upload limit; "0" refers to the field's own value */
            "hsFieldUploadQuotaHint" => $state && isset($ac->text->hsFieldUploadQuotaHint) ? $ac->text->hsFieldUploadQuotaHint : __('A fixed total that ignores how many fields the form has. 0 keeps the automatic per-field calculation.', 'flx-forms'),
            /* translators: Field label - how long before a visitor's upload allowance resets */
            "hsFieldUploadWindowLabel" => $state && isset($ac->text->hsFieldUploadWindowLabel) ? $ac->text->hsFieldUploadWindowLabel : __('Budget resets after (seconds)', 'flx-forms'),
            /* translators: Hint for the upload budget reset window; "0" refers to the field's own value */
            "hsFieldUploadWindowHint" => $state && isset($ac->text->hsFieldUploadWindowHint) ? $ac->text->hsFieldUploadWindowHint : __('Default 3600 (one hour). 0 keeps the default.', 'flx-forms'),
            /* translators: Field label - a site-wide maximum upload size in megabytes */
            "hsFieldUploadMaxMbLabel" => $state && isset($ac->text->hsFieldUploadMaxMbLabel) ? $ac->text->hsFieldUploadMaxMbLabel : __('Maximum file size (MB)', 'flx-forms'),
            /* translators: Hint for the site-wide maximum upload size; "0" refers to the field's own value */
            "hsFieldUploadMaxMbHint" => $state && isset($ac->text->hsFieldUploadMaxMbHint) ? $ac->text->hsFieldUploadMaxMbHint : __('A site-wide ceiling. It can only tighten a per-field limit, never widen it. 0 keeps the field setting.', 'flx-forms'),
            /* translators: Toggle label - rate-limit tracking-code lookups without requiring a human token */
            "hsFieldProtectLookupLabel" => $state && isset($ac->text->hsFieldProtectLookupLabel) ? $ac->text->hsFieldProtectLookupLabel : __('Protect response lookup', 'flx-forms'),
            /* translators: Hint for the response-lookup protection toggle */
            "hsFieldProtectLookupHint" => $state && isset($ac->text->hsFieldProtectLookupHint) ? $ac->text->hsFieldProtectLookupHint : __('Rate-limit tracking-code searches by request count (no human-token check).', 'flx-forms'),
            /* translators: Section title for the manually managed IP allow/block lists */
            "hsManualListsTitle" => $state && isset($ac->text->hsManualListsTitle) ? $ac->text->hsManualListsTitle : __('Manual access lists', 'flx-forms'),
            /* translators: Description of the manual IP allow/block lists, with a wildcard example (203.0.113.*) */
            "hsManualListsDesc" => $state && isset($ac->text->hsManualListsDesc) ? $ac->text->hsManualListsDesc : __('One entry per line: an exact IP or a wildcard prefix such as 203.0.113.* — allowlist bypasses all checks, blocklist blocks in every mode.', 'flx-forms'),
            /* translators: Field label - IPs that always bypass the security checks */
            "hsFieldAllowlistLabel" => $state && isset($ac->text->hsFieldAllowlistLabel) ? $ac->text->hsFieldAllowlistLabel : __('IP allowlist', 'flx-forms'),
            /* translators: Hint for the IP allowlist field */
            "hsFieldAllowlistHint" => $state && isset($ac->text->hsFieldAllowlistHint) ? $ac->text->hsFieldAllowlistHint : __('Trusted IPs that skip the shield completely (your office, monitoring bots).', 'flx-forms'),
            /* translators: Field label - IPs that are always blocked */
            "hsFieldBlocklistLabel" => $state && isset($ac->text->hsFieldBlocklistLabel) ? $ac->text->hsFieldBlocklistLabel : __('IP blocklist', 'flx-forms'),
            /* translators: Hint for the IP blocklist field */
            "hsFieldBlocklistHint" => $state && isset($ac->text->hsFieldBlocklistHint) ? $ac->text->hsFieldBlocklistHint : __('IPs that are always blocked on protected form routes.', 'flx-forms'),


            /* translators: Field label - minimum score required to send a paid notification */
            "hsFieldMinPaidScoreLabel" => $state && isset($ac->text->hsFieldMinPaidScoreLabel) ? $ac->text->hsFieldMinPaidScoreLabel : __('Minimum paid notification score', 'flx-forms'),
            /* translators: Hint for the minimum paid-notification score field */
            "hsFieldMinPaidScoreHint" => $state && isset($ac->text->hsFieldMinPaidScoreHint) ? $ac->text->hsFieldMinPaidScoreHint : __('Suppress costly notifications below this score.', 'flx-forms'),
            /* translators: Field label - daily budget cap for outgoing SMS notifications */
            "hsFieldSmsStopLossLabel" => $state && isset($ac->text->hsFieldSmsStopLossLabel) ? $ac->text->hsFieldSmsStopLossLabel : __('SMS daily stop-loss', 'flx-forms'),
            /* translators: Hint for the SMS daily stop-loss field */
            "hsFieldSmsStopLossHint" => $state && isset($ac->text->hsFieldSmsStopLossHint) ? $ac->text->hsFieldSmsStopLossHint : __('Global SMS budget per day, across all recipients.', 'flx-forms'),
            /* translators: Field label - daily budget cap for outgoing Telegram notifications */
            "hsFieldTelegramStopLossLabel" => $state && isset($ac->text->hsFieldTelegramStopLossLabel) ? $ac->text->hsFieldTelegramStopLossLabel : __('Telegram daily stop-loss', 'flx-forms'),
            /* translators: Hint for the Telegram daily stop-loss field */
            "hsFieldTelegramStopLossHint" => $state && isset($ac->text->hsFieldTelegramStopLossHint) ? $ac->text->hsFieldTelegramStopLossHint : __('Global Telegram budget per day, across all recipients.', 'flx-forms'),
            /* translators: Field label - daily budget cap for outgoing webhook/email/sheet notifications */
            "hsFieldWebhookStopLossLabel" => $state && isset($ac->text->hsFieldWebhookStopLossLabel) ? $ac->text->hsFieldWebhookStopLossLabel : __('Webhook/Email daily stop-loss', 'flx-forms'),
            /* translators: Hint for the webhook/email daily stop-loss field */
            "hsFieldWebhookStopLossHint" => $state && isset($ac->text->hsFieldWebhookStopLossHint) ? $ac->text->hsFieldWebhookStopLossHint : __('Global webhook/email/sheet budget per day.', 'flx-forms'),
            /* translators: Field label - maximum notifications a single recipient can get per day */
            "hsFieldRecipientCapLabel" => $state && isset($ac->text->hsFieldRecipientCapLabel) ? $ac->text->hsFieldRecipientCapLabel : __('Per-recipient daily cap', 'flx-forms'),
            /* translators: Hint for the per-recipient daily cap field; "0" refers to the field's own value */
            "hsFieldRecipientCapHint" => $state && isset($ac->text->hsFieldRecipientCapHint) ? $ac->text->hsFieldRecipientCapHint : __('Maximum notifications per single recipient per day (0 disables).', 'flx-forms'),
            /* translators: Note shown under the paid stop-loss settings until the notification filter ships */
            "hsPaidStopLossNote" => $state && isset($ac->text->hsPaidStopLossNote) ? $ac->text->hsPaidStopLossNote : __('These limits become active for paid services after the core integration filter is added.', 'flx-forms'),

            /* translators: Chart title on the Logs tab showing hourly decision counts */
            "hsDecisionsChartTitle" => $state && isset($ac->text->hsDecisionsChartTitle) ? $ac->text->hsDecisionsChartTitle : __('Decisions — last 24 hours', 'flx-forms'),
            /* translators: Chart subtitle explaining the stacked columns */
            "hsDecisionsChartDesc" => $state && isset($ac->text->hsDecisionsChartDesc) ? $ac->text->hsDecisionsChartDesc : __('Stacked per hour: allowed, blocked, quarantined and monitored requests.', 'flx-forms'),
            /* translators: Empty state when there is no chart data yet */
            "hsNoDecisions24h" => $state && isset($ac->text->hsNoDecisions24h) ? $ac->text->hsNoDecisions24h : __('No decisions recorded in the last 24 hours.', 'flx-forms'),
            /* translators: Logs tab table card title */
            "hsSecurityLogsTitle" => $state && isset($ac->text->hsSecurityLogsTitle) ? $ac->text->hsSecurityLogsTitle : __('Security logs', 'flx-forms'),
            /* translators: Logs tab table card subtitle */
            "hsSecurityLogsDesc" => $state && isset($ac->text->hsSecurityLogsDesc) ? $ac->text->hsSecurityLogsDesc : __('Recent decisions without storing raw IP addresses.', 'flx-forms'),
            /* translators: Button that downloads the security logs as a CSV file */
            "hsExportCsv" => $state && isset($ac->text->hsExportCsv) ? $ac->text->hsExportCsv : __('Export CSV', 'flx-forms'),
            /* translators: Button that reloads the logs list */
            "hsRefresh" => $state && isset($ac->text->hsRefresh) ? $ac->text->hsRefresh : __('Refresh', 'flx-forms'),
            /* translators: Confirmation prompt before deleting all stored security logs */
            "hsConfirmClearLogs" => $state && isset($ac->text->hsConfirmClearLogs) ? $ac->text->hsConfirmClearLogs : __('Clear Human Shield logs?', 'flx-forms'),
            /* translators: Success message after clearing the security logs */
            "hsLogsCleared" => $state && isset($ac->text->hsLogsCleared) ? $ac->text->hsLogsCleared : __('Logs cleared.', 'flx-forms'),
            /* translators: Card title listing recent activity on the Overview tab */
            "hsRecentActivityTitle" => $state && isset($ac->text->hsRecentActivityTitle) ? $ac->text->hsRecentActivityTitle : __('Recent activity', 'flx-forms'),
            /* translators: Card subtitle for the recent activity list */
            "hsRecentActivityDesc" => $state && isset($ac->text->hsRecentActivityDesc) ? $ac->text->hsRecentActivityDesc : __('Latest security decisions.', 'flx-forms'),
            /* translators: Empty state when there are no log entries at all */
            "hsNoLogsYet" => $state && isset($ac->text->hsNoLogsYet) ? $ac->text->hsNoLogsYet : __('No logs yet.', 'flx-forms'),
            /* translators: Logs table column - when the decision was made */
            "hsColDate" => $state && isset($ac->text->hsColDate) ? $ac->text->hsColDate : __('Date', 'flx-forms'),
            /* translators: Logs table column - which REST route the request hit */
            "hsColRoute" => $state && isset($ac->text->hsColRoute) ? $ac->text->hsColRoute : __('Route', 'flx-forms'),
            /* translators: Logs table column - which form the request belonged to */
            "hsColForm" => $state && isset($ac->text->hsColForm) ? $ac->text->hsColForm : __('Form', 'flx-forms'),
            /* translators: Logs table column - allow/block/quarantine/monitor outcome */
            "hsColDecision" => $state && isset($ac->text->hsColDecision) ? $ac->text->hsColDecision : __('Decision', 'flx-forms'),
            /* translators: Logs table column - the numeric behavior score */
            "hsColScore" => $state && isset($ac->text->hsColScore) ? $ac->text->hsColScore : __('Score', 'flx-forms'),
            /* translators: Logs table column - the codes explaining why a decision was made */
            "hsColReasons" => $state && isset($ac->text->hsColReasons) ? $ac->text->hsColReasons : __('Reasons', 'flx-forms'),

            /* translators: System tab card title */
            "hsSystemReadinessTitle" => $state && isset($ac->text->hsSystemReadinessTitle) ? $ac->text->hsSystemReadinessTitle : __('System readiness', 'flx-forms'),
            /* translators: System tab card subtitle */
            "hsSystemReadinessDesc" => $state && isset($ac->text->hsSystemReadinessDesc) ? $ac->text->hsSystemReadinessDesc : __('Human Shield checks these functions before enforcing security rules.', 'flx-forms'),
            /* translators: Shown when every required PHP function is available */
            "hsPhpReqReady" => $state && isset($ac->text->hsPhpReqReady) ? $ac->text->hsPhpReqReady : __('PHP requirements are ready.', 'flx-forms'),
            /* translators: Shown when at least one required PHP function is disabled */
            "hsPhpReqNotReady" => $state && isset($ac->text->hsPhpReqNotReady) ? $ac->text->hsPhpReqNotReady : __('Some required PHP functions are unavailable.', 'flx-forms'),
            /* translators: Label preceding the REST API availability status (e.g. "REST API: available") */
            "hsRestApiLabel" => $state && isset($ac->text->hsRestApiLabel) ? $ac->text->hsRestApiLabel : __('REST API:', 'flx-forms'),
            /* translators: REST API status value - the REST API responds normally */
            "hsRestAvailable" => $state && isset($ac->text->hsRestAvailable) ? $ac->text->hsRestAvailable : __('available', 'flx-forms'),
            /* translators: REST API status value - the REST API cannot be reached */
            "hsRestMissing" => $state && isset($ac->text->hsRestMissing) ? $ac->text->hsRestMissing : __('missing', 'flx-forms'),
            /* translators: Label preceding the database tables status (e.g. "Tables: ready") */
            "hsTablesLabel" => $state && isset($ac->text->hsTablesLabel) ? $ac->text->hsTablesLabel : __('Tables:', 'flx-forms'),
            /* translators: Database tables status value - the add-on's tables exist */
            "hsTablesReady" => $state && isset($ac->text->hsTablesReady) ? $ac->text->hsTablesReady : __('ready', 'flx-forms'),
            /* translators: Database tables status value - the add-on's tables have not been created yet */
            "hsTablesNotReady" => $state && isset($ac->text->hsTablesNotReady) ? $ac->text->hsTablesNotReady : __('not ready yet', 'flx-forms'),
            /* translators: Hint for the fail-closed toggle */
            "hsFieldFailClosedHint" => $state && isset($ac->text->hsFieldFailClosedHint) ? $ac->text->hsFieldFailClosedHint : __('Recommended only after you verify PHP functions and tables are ready.', 'flx-forms'),
            /* translators: Toggle label - trust reverse-proxy headers for the visitor's real IP address */
            "hsFieldTrustProxyLabel" => $state && isset($ac->text->hsFieldTrustProxyLabel) ? $ac->text->hsFieldTrustProxyLabel : __('Trust proxy IP headers', 'flx-forms'),
            /* translators: Hint for the trust-proxy-headers toggle */
            "hsFieldTrustProxyHint" => $state && isset($ac->text->hsFieldTrustProxyHint) ? $ac->text->hsFieldTrustProxyHint : __('Use forwarding headers only when the immediate peer matches the trusted proxy list below.', 'flx-forms'),
            /* translators: Field label - IP addresses of trusted reverse proxies (load balancer, CDN) */
            "hsFieldTrustedProxyIpsLabel" => $state && isset($ac->text->hsFieldTrustedProxyIpsLabel) ? $ac->text->hsFieldTrustedProxyIpsLabel : __('Trusted proxy IPs', 'flx-forms'),
            /* translators: Hint for the trusted proxy IPs field */
            "hsFieldTrustedProxyIpsHint" => $state && isset($ac->text->hsFieldTrustedProxyIpsHint) ? $ac->text->hsFieldTrustedProxyIpsHint : __('One exact IP or wildcard prefix per line. Add only your load balancer/CDN egress addresses; keep this empty when the origin is directly reachable.', 'flx-forms'),
            /* translators: Toggle label - keep raw per-request behavior metrics for debugging */
            "hsFieldStoreRawMetricsLabel" => $state && isset($ac->text->hsFieldStoreRawMetricsLabel) ? $ac->text->hsFieldStoreRawMetricsLabel : __('Store raw behavior metrics', 'flx-forms'),
            /* translators: Hint for the store-raw-metrics toggle */
            "hsFieldStoreRawMetricsHint" => $state && isset($ac->text->hsFieldStoreRawMetricsHint) ? $ac->text->hsFieldStoreRawMetricsHint : __('Keep off for privacy unless debugging a rollout.', 'flx-forms'),
            /* translators: Field label - number of days security log entries are kept */
            "hsFieldLogRetentionLabel" => $state && isset($ac->text->hsFieldLogRetentionLabel) ? $ac->text->hsFieldLogRetentionLabel : __('Log retention days', 'flx-forms'),
            /* translators: Hint for the log retention field */
            "hsFieldLogRetentionHint" => $state && isset($ac->text->hsFieldLogRetentionHint) ? $ac->text->hsFieldLogRetentionHint : __('How long events should be retained.', 'flx-forms'),
            /* translators: Field label - timeout in milliseconds for the browser's client-side attestation check */
            "hsFieldClientAttestTimeoutLabel" => $state && isset($ac->text->hsFieldClientAttestTimeoutLabel) ? $ac->text->hsFieldClientAttestTimeoutLabel : __('Client attestation timeout', 'flx-forms'),
            /* translators: Hint for the client attestation timeout field */
            "hsFieldClientAttestTimeoutHint" => $state && isset($ac->text->hsFieldClientAttestTimeoutHint) ? $ac->text->hsFieldClientAttestTimeoutHint : __('Milliseconds before the browser falls back to the normal request.', 'flx-forms'),
            /* translators: Note at the bottom of the System tab about disabled PHP functions and cache/security plugin conflicts; the path is a REST API route and must stay untranslated */
            "hsSystemNote" => $state && isset($ac->text->hsSystemNote) ? $ac->text->hsSystemNote : __('If a required PHP function is disabled, enable it in php.ini disable_functions or ask the host to allow it. Cache and security plugins must not cache or rewrite POST responses from /wp-json/EmsfbShield/v1/*.', 'flx-forms'),
            /* translators: Marks a PHP function as mandatory in the system requirements list */
            "hsFunctionRequired" => $state && isset($ac->text->hsFunctionRequired) ? $ac->text->hsFunctionRequired : __('Required', 'flx-forms'),
            /* translators: Marks a PHP function as optional in the system requirements list */
            "hsFunctionRecommended" => $state && isset($ac->text->hsFunctionRecommended) ? $ac->text->hsFunctionRecommended : __('Recommended', 'flx-forms'),
            /* translators: Screen-reader label for the tab navigation region */
            "hsTabsAriaLabel" => $state && isset($ac->text->hsTabsAriaLabel) ? $ac->text->hsTabsAriaLabel : __('Security & Spam Protection tabs', 'flx-forms'),

            /* translators: %s = comma-separated list of missing PHP function names. CSV export error, singular (paired with hsExportMissingFnPlural via _n()) */
            "hsExportMissingFnSingular" => $state && isset($ac->text->hsExportMissingFnSingular) ? $ac->text->hsExportMissingFnSingular : __('Security log export is unavailable because this server has disabled a required PHP function: %s. Ask your hosting provider to enable it.', 'flx-forms'),
            /* translators: %s = comma-separated list of missing PHP function names. CSV export error, plural (paired with hsExportMissingFnSingular via _n()) */
            "hsExportMissingFnPlural" => $state && isset($ac->text->hsExportMissingFnPlural) ? $ac->text->hsExportMissingFnPlural : __('Security log export is unavailable because this server has disabled required PHP functions: %s. Ask your hosting provider to enable them.', 'flx-forms'),
        ];
    }

    public static function get_autofill_phrases($ac = null, $state = false) {
        return [

            /* translators: Auto-Populate Dataset = menu title for dataset management */
            "autofill_dataset" => $state && isset($ac->text->autofill_dataset) ? $ac->text->autofill_dataset : esc_html__('Auto-Populate Dataset', 'flx-forms'),


            /* translators: Dataset page header subtitle */
            "datasetSubtitle" => $state && isset($ac->text->datasetSubtitle) ? $ac->text->datasetSubtitle : esc_html__('Manage & Upload Datasets', 'flx-forms'),

            /* translators: Feature badge: Upload */
            "uploadDataset" => $state && isset($ac->text->uploadDataset) ? $ac->text->uploadDataset : esc_html__('Upload CSV', 'flx-forms'),

            /* translators: Feature badge: Manage */
            "manageDatasets" => $state && isset($ac->text->manageDatasets) ? $ac->text->manageDatasets : esc_html__('Manage Datasets', 'flx-forms'),

            /* translators: Feature badge: Easy Edit */
            "easyEdit" => $state && isset($ac->text->easyEdit) ? $ac->text->easyEdit : esc_html__('Easy Edit', 'flx-forms'),

            /* translators: Total datasets status label */
            "totalDatasets" => $state && isset($ac->text->totalDatasets) ? $ac->text->totalDatasets : esc_html__('Total Datasets', 'flx-forms'),

            /* translators: Datasets count suffix */
            "datasets" => $state && isset($ac->text->datasets) ? $ac->text->datasets : esc_html__('Datasets', 'flx-forms'),

            /* translators: Dataset status hint */
            "datasetStatusHint" => $state && isset($ac->text->datasetStatusHint) ? $ac->text->datasetStatusHint : esc_html__('Datasets available for auto-populate', 'flx-forms'),

            /* translators: Tab: Datasets */
            "datasetsTab" => $state && isset($ac->text->datasetsTab) ? $ac->text->datasetsTab : esc_html__('Datasets', 'flx-forms'),

            /* translators: Tab: Help */
            "helpTab" => $state && isset($ac->text->helpTab) ? $ac->text->helpTab : esc_html__('Help', 'flx-forms'),

            /* translators: No datasets yet message */
            "noDatasetsYet" => $state && isset($ac->text->noDatasetsYet) ? $ac->text->noDatasetsYet : esc_html__('No datasets yet. Upload a CSV file to get started.', 'flx-forms'),

            /* translators: Dataset help title */
            "datasetHelpTitle" => $state && isset($ac->text->datasetHelpTitle) ? $ac->text->datasetHelpTitle : esc_html__('How to use Auto-Populate Datasets', 'flx-forms'),

            /* translators: Dataset help step 1 */
            "datasetHelpStep1" => $state && isset($ac->text->datasetHelpStep1) ? $ac->text->datasetHelpStep1 : esc_html__('Prepare a CSV file with column headers in the first row.', 'flx-forms'),

            /* translators: Dataset help step 1 title */
            "datasetHelpStep1Title" => $state && isset($ac->text->datasetHelpStep1Title) ? $ac->text->datasetHelpStep1Title : esc_html__('Prepare CSV', 'flx-forms'),

            /* translators: Dataset help step 2 */
            "datasetHelpStep2" => $state && isset($ac->text->datasetHelpStep2) ? $ac->text->datasetHelpStep2 : esc_html__('Upload the CSV file using the upload button.', 'flx-forms'),

            /* translators: Dataset help step 2 title */
            "datasetHelpStep2Title" => $state && isset($ac->text->datasetHelpStep2Title) ? $ac->text->datasetHelpStep2Title : esc_html__('Upload Dataset', 'flx-forms'),

            /* translators: Dataset help step 3 */
            "datasetHelpStep3" => $state && isset($ac->text->datasetHelpStep3) ? $ac->text->datasetHelpStep3 : esc_html__('In Form Builder, open Form Settings and enable "Auto-Populate". Choose your dataset, then map elements to dataset fields as search conditions.', 'flx-forms'),

            /* translators: Dataset help step 3 title */
            "datasetHelpStep3Title" => $state && isset($ac->text->datasetHelpStep3Title) ? $ac->text->datasetHelpStep3Title : esc_html__('Enable Auto-Populate', 'flx-forms'),

            /* translators: Dataset help step 4 */
            "datasetHelpStep4" => $state && isset($ac->text->datasetHelpStep4) ? $ac->text->datasetHelpStep4 : esc_html__('For each target field, enable "Auto-Populate to automatically populate this field" and select the dataset column to auto-populate.', 'flx-forms'),

            /* translators: Dataset help step 4 title */
            "datasetHelpStep4Title" => $state && isset($ac->text->datasetHelpStep4Title) ? $ac->text->datasetHelpStep4Title : esc_html__('Map Fields', 'flx-forms'),

            /* translators: Dataset help tip */
            "datasetHelpTip" => $state && isset($ac->text->datasetHelpTip) ? $ac->text->datasetHelpTip : esc_html__('You can edit dataset values inline by clicking on them.', 'flx-forms'),

            /* translators: Dataset help subtitle */
            "datasetHelpSubtitle" => $state && isset($ac->text->datasetHelpSubtitle) ? $ac->text->datasetHelpSubtitle : esc_html__('Follow these steps to set up auto-populate for your forms', 'flx-forms'),

            /* translators: Quick Start Guide title */
            "quickStartGuide" => $state && isset($ac->text->quickStartGuide) ? $ac->text->quickStartGuide : esc_html__('Quick Start Guide', 'flx-forms'),

            /* translators: Tips & Notes title */
            "tipsAndNotes" => $state && isset($ac->text->tipsAndNotes) ? $ac->text->tipsAndNotes : esc_html__('Tips & Notes', 'flx-forms'),

            /* translators: Editing Data section title */
            "editingData" => $state && isset($ac->text->editingData) ? $ac->text->editingData : esc_html__('Editing Data', 'flx-forms'),

            /* translators: Dataset tip about CSV format */
            "datasetTipCSV" => $state && isset($ac->text->datasetTipCSV) ? $ac->text->datasetTipCSV : esc_html__('Supported format: CSV with UTF-8 encoding.', 'flx-forms'),

            /* translators: Form Builder Tips section title */
            "formBuilderTips" => $state && isset($ac->text->formBuilderTips) ? $ac->text->formBuilderTips : esc_html__('Form Builder Tips', 'flx-forms'),

            /* translators: Dataset tip about conditions */
            "datasetTipCondition" => $state && isset($ac->text->datasetTipCondition) ? $ac->text->datasetTipCondition : esc_html__('Use conditions to filter dataset rows based on user input.', 'flx-forms'),

            /* translators: Dataset tip about multiple fields */
            "datasetTipMultiple" => $state && isset($ac->text->datasetTipMultiple) ? $ac->text->datasetTipMultiple : esc_html__('Multiple fields can auto-populate from the same dataset.', 'flx-forms'),

            /* translators: Dataset notice title */
            "datasetNoticeTitle" => $state && isset($ac->text->datasetNoticeTitle) ? $ac->text->datasetNoticeTitle : esc_html__('Good to know', 'flx-forms'),

            /* translators: Dataset notice text */
            "datasetNoticeText" => $state && isset($ac->text->datasetNoticeText) ? $ac->text->datasetNoticeText : esc_html__('Changes to the dataset take effect immediately for new form submissions.', 'flx-forms'),

            /* translators: API page subtitle */
            "apiSubtitle" => $state && isset($ac->text->apiSubtitle) ? $ac->text->apiSubtitle : esc_html__('Connect & Manage API Integrations', 'flx-forms'),

            /* translators: Feature badge: Real-time */
            "realTimeData" => $state && isset($ac->text->realTimeData) ? $ac->text->realTimeData : esc_html__('Real-time Data', 'flx-forms'),

            /* translators: Feature badge: Field Mapping */
            "fieldMappingFeature" => $state && isset($ac->text->fieldMappingFeature) ? $ac->text->fieldMappingFeature : esc_html__('Field Mapping', 'flx-forms'),

            /* translators: Feature badge: Smart Cache */
            "smartCache" => $state && isset($ac->text->smartCache) ? $ac->text->smartCache : esc_html__('Smart Cache', 'flx-forms'),

            /* translators: Total connections label */
            "totalConnections" => $state && isset($ac->text->totalConnections) ? $ac->text->totalConnections : esc_html__('Total Connections', 'flx-forms'),

            /* translators: Connections count suffix */
            "connections" => $state && isset($ac->text->connections) ? $ac->text->connections : esc_html__('Connections', 'flx-forms'),

            /* translators: API status hint */
            "apiStatusHint" => $state && isset($ac->text->apiStatusHint) ? $ac->text->apiStatusHint : esc_html__('API connections configured', 'flx-forms'),

            /* translators: Tab: Connections */
            "connectionsTab" => $state && isset($ac->text->connectionsTab) ? $ac->text->connectionsTab : esc_html__('Connections', 'flx-forms'),

            /* translators: Tab: Help */
            "apiHelpTab" => $state && isset($ac->text->apiHelpTab) ? $ac->text->apiHelpTab : esc_html__('Help', 'flx-forms'),

            /* translators: API help title */
            "apiHelpTitle" => $state && isset($ac->text->apiHelpTitle) ? $ac->text->apiHelpTitle : esc_html__('How to use API Integrations', 'flx-forms'),

            /* translators: API help subtitle */
            "apiHelpSubtitle" => $state && isset($ac->text->apiHelpSubtitle) ? $ac->text->apiHelpSubtitle : esc_html__('Follow these steps to connect your external API', 'flx-forms'),

            /* translators: API help step 1 */
            "apiHelpStep1" => $state && isset($ac->text->apiHelpStep1) ? $ac->text->apiHelpStep1 : esc_html__('Click "Add API Connection" to create a new connection and enter your API endpoint URL.', 'flx-forms'),

            /* translators: API help step 1 title */
            "apiHelpStep1Title" => $state && isset($ac->text->apiHelpStep1Title) ? $ac->text->apiHelpStep1Title : esc_html__('Add Connection', 'flx-forms'),

            /* translators: API help step 2 */
            "apiHelpStep2" => $state && isset($ac->text->apiHelpStep2) ? $ac->text->apiHelpStep2 : esc_html__('Configure authentication (API Key, Bearer Token, or Basic Auth) if your API requires it.', 'flx-forms'),

            /* translators: API help step 2 title */
            "apiHelpStep2Title" => $state && isset($ac->text->apiHelpStep2Title) ? $ac->text->apiHelpStep2Title : esc_html__('Authentication', 'flx-forms'),

            /* translators: API help step 3 */
            "apiHelpStep3" => $state && isset($ac->text->apiHelpStep3) ? $ac->text->apiHelpStep3 : esc_html__('Select a form and map API response fields to your form fields for auto-populate.', 'flx-forms'),

            /* translators: API help step 3 title */
            "apiHelpStep3Title" => $state && isset($ac->text->apiHelpStep3Title) ? $ac->text->apiHelpStep3Title : esc_html__('Map Fields', 'flx-forms'),

            /* translators: API help step 4 */
            "apiHelpStep4" => $state && isset($ac->text->apiHelpStep4) ? $ac->text->apiHelpStep4 : esc_html__('Use the Test button to verify the API returns expected data, then save your connection.', 'flx-forms'),

            /* translators: API help step 4 title */
            "apiHelpStep4Title" => $state && isset($ac->text->apiHelpStep4Title) ? $ac->text->apiHelpStep4Title : esc_html__('Test & Save', 'flx-forms'),

            /* translators: API help tip */
            "apiHelpTip" => $state && isset($ac->text->apiHelpTip) ? $ac->text->apiHelpTip : esc_html__('Use cache settings to reduce API calls and improve performance.', 'flx-forms'),

            /* translators: API tip - Performance section title */
            "apiTipPerformance" => $state && isset($ac->text->apiTipPerformance) ? $ac->text->apiTipPerformance : esc_html__('Performance', 'flx-forms'),

            /* translators: API tip - timeout */
            "apiTipTimeout" => $state && isset($ac->text->apiTipTimeout) ? $ac->text->apiTipTimeout : esc_html__('Set a reasonable timeout to prevent slow form loading.', 'flx-forms'),

            /* translators: API tip - Security section title */
            "apiTipSecurity" => $state && isset($ac->text->apiTipSecurity) ? $ac->text->apiTipSecurity : esc_html__('Security', 'flx-forms'),

            /* translators: API tip - HTTPS */
            "apiTipAuth" => $state && isset($ac->text->apiTipAuth) ? $ac->text->apiTipAuth : esc_html__('Always use HTTPS endpoints for secure data transfer.', 'flx-forms'),

            /* translators: API tip - keys */
            "apiTipKeys" => $state && isset($ac->text->apiTipKeys) ? $ac->text->apiTipKeys : esc_html__('Keep API keys confidential — they are stored securely in WordPress.', 'flx-forms'),

            /* translators: API notice title */
            "apiNoticeTitle" => $state && isset($ac->text->apiNoticeTitle) ? $ac->text->apiNoticeTitle : esc_html__('Good to know', 'flx-forms'),

            /* translators: API notice text */
            "apiNoticeText" => $state && isset($ac->text->apiNoticeText) ? $ac->text->apiNoticeText : esc_html__('API responses are fetched in real-time when the form loads. Enable caching for frequently accessed data.', 'flx-forms'),

            /* translators: External API Connections = section header */
            "external_api" => $state && isset($ac->text->external_api) ? $ac->text->external_api : esc_html__('External API Connections', 'flx-forms'),

            /* translators: Add New API Connection = button text */
            "add_new_api" => $state && isset($ac->text->add_new_api) ? $ac->text->add_new_api : esc_html__('Add New API Connection', 'flx-forms'),

            /* translators: Edit API Connection = modal title */
            "edit_api_connection" => $state && isset($ac->text->edit_api_connection) ? $ac->text->edit_api_connection : esc_html__('Edit API Connection', 'flx-forms'),

            /* translators: Connection Name = field label */
            "connection_name" => $state && isset($ac->text->connection_name) ? $ac->text->connection_name : esc_html__('Connection Name', 'flx-forms'),

            /* translators: Connection Name placeholder */
            "connection_name_placeholder" => $state && isset($ac->text->connection_name_placeholder) ? $ac->text->connection_name_placeholder : esc_html__('e.g., Customer Lookup API', 'flx-forms'),

            /* translators: HTTP Method = field label */
            "http_method" => $state && isset($ac->text->http_method) ? $ac->text->http_method : esc_html__('HTTP Method', 'flx-forms'),

            /* translators: API Endpoint URL = field label */
            "endpoint_url" => $state && isset($ac->text->endpoint_url) ? $ac->text->endpoint_url : esc_html__('API Endpoint URL', 'flx-forms'),

            /* translators: Endpoint URL help text */
            "endpoint_url_help" => $state && isset($ac->text->endpoint_url_help) ? $ac->text->endpoint_url_help : esc_html__('Use {{field_id}} placeholders for dynamic values from form fields', 'flx-forms'),

            /* translators: Authentication = section title */
            "authentication" => $state && isset($ac->text->authentication) ? $ac->text->authentication : esc_html__('Authentication', 'flx-forms'),

            /* translators: Authentication Type = field label */
            "auth_type" => $state && isset($ac->text->auth_type) ? $ac->text->auth_type : esc_html__('Authentication Type', 'flx-forms'),

            /* translators: No Authentication = auth option */
            "no_auth" => $state && isset($ac->text->no_auth) ? $ac->text->no_auth : esc_html__('No Authentication', 'flx-forms'),

            /* translators: Custom Header = auth option */
            "custom_header" => $state && isset($ac->text->custom_header) ? $ac->text->custom_header : esc_html__('Custom Header', 'flx-forms'),

            /* translators: Authentication Value = field label */
            "auth_value" => $state && isset($ac->text->auth_value) ? $ac->text->auth_value : esc_html__('Authentication Value', 'flx-forms'),

            /* translators: Auth Value placeholder */
            "auth_value_placeholder" => $state && isset($ac->text->auth_value_placeholder) ? $ac->text->auth_value_placeholder : esc_html__('Enter token or credentials', 'flx-forms'),

            /* translators: Bearer token help */
            "bearer_help" => $state && isset($ac->text->bearer_help) ? $ac->text->bearer_help : esc_html__('Enter your Bearer token without "Bearer " prefix', 'flx-forms'),

            /* translators: Basic auth help */
            "basic_help" => $state && isset($ac->text->basic_help) ? $ac->text->basic_help : esc_html__('Enter as username:password', 'flx-forms'),

            /* translators: API key help */
            "api_key_help" => $state && isset($ac->text->api_key_help) ? $ac->text->api_key_help : esc_html__('Enter your API key', 'flx-forms'),

            /* translators: Custom auth help */
            "custom_auth_help" => $state && isset($ac->text->custom_auth_help) ? $ac->text->custom_auth_help : esc_html__('Enter as Header-Name: value', 'flx-forms'),

            /* translators: Custom Headers = section title */
            "custom_headers" => $state && isset($ac->text->custom_headers) ? $ac->text->custom_headers : esc_html__('Custom Headers', 'flx-forms'),

            /* translators: No custom headers message */
            "no_custom_headers" => $state && isset($ac->text->no_custom_headers) ? $ac->text->no_custom_headers : esc_html__('No custom headers', 'flx-forms'),

            /* translators: Query Parameters = section title */
            "query_params" => $state && isset($ac->text->query_params) ? $ac->text->query_params : esc_html__('Query Parameters', 'flx-forms'),

            /* translators: No query params message */
            "no_query_params" => $state && isset($ac->text->no_query_params) ? $ac->text->no_query_params : esc_html__('No query parameters', 'flx-forms'),

            /* translators: Request Body Template = field label */
            "request_body" => $state && isset($ac->text->request_body) ? $ac->text->request_body : esc_html__('Request Body Template (JSON)', 'flx-forms'),

            /* translators: Body template help */
            "body_template_help" => $state && isset($ac->text->body_template_help) ? $ac->text->body_template_help : esc_html__('JSON template for POST/PUT/PATCH requests. Use {{field_id}} for dynamic values.', 'flx-forms'),

            /* translators: Response Mapping = section title */
            "response_mapping" => $state && isset($ac->text->response_mapping) ? $ac->text->response_mapping : esc_html__('Response Mapping', 'flx-forms'),

            /* translators: Response Data Path = field label */
            "response_path" => $state && isset($ac->text->response_path) ? $ac->text->response_path : esc_html__('Response Data Path', 'flx-forms'),

            /* translators: Response path help */
            "response_path_help" => $state && isset($ac->text->response_path_help) ? $ac->text->response_path_help : esc_html__('Dot notation path to the data array in response. e.g., data.results or items', 'flx-forms'),

            /* translators: Field Mappings = section title */
            "field_mappings" => $state && isset($ac->text->field_mappings) ? $ac->text->field_mappings : esc_html__('Field Mappings', 'flx-forms'),

            /* translators: No field mappings message */
            "no_field_mappings" => $state && isset($ac->text->no_field_mappings) ? $ac->text->no_field_mappings : esc_html__('No field mappings', 'flx-forms'),

            /* translators: Field mappings help */
            "field_mappings_help" => $state && isset($ac->text->field_mappings_help) ? $ac->text->field_mappings_help : esc_html__('Map API response fields to form field IDs', 'flx-forms'),

            /* translators: Cache Settings = section title */
            "cache_settings" => $state && isset($ac->text->cache_settings) ? $ac->text->cache_settings : esc_html__('Cache Settings', 'flx-forms'),

            /* translators: Cache Duration = field label */
            "cache_duration" => $state && isset($ac->text->cache_duration) ? $ac->text->cache_duration : esc_html__('Cache Duration (minutes)', 'flx-forms'),

            /* translators: Cache duration help */
            "cache_duration_help" => $state && isset($ac->text->cache_duration_help) ? $ac->text->cache_duration_help : esc_html__('0 = no caching', 'flx-forms'),

            /* translators: Basic Information = section title */
            "basic_info" => $state && isset($ac->text->basic_info) ? $ac->text->basic_info : esc_html__('Basic Information', 'flx-forms'),

            /* translators: Test Connection = button text */
            "test_connection" => $state && isset($ac->text->test_connection) ? $ac->text->test_connection : esc_html__('Test Connection', 'flx-forms'),

            /* translators: Test Result = section title */
            "test_result" => $state && isset($ac->text->test_result) ? $ac->text->test_result : esc_html__('Test Result', 'flx-forms'),

            /* translators: Testing connection message */
            "testing" => $state && isset($ac->text->testing) ? $ac->text->testing : esc_html__('Testing connection...', 'flx-forms'),

            /* translators: Endpoint = column header */
            "endpoint" => $state && isset($ac->text->endpoint) ? $ac->text->endpoint : esc_html__('Endpoint', 'flx-forms'),

            /* translators: Actions = column header */
            "actions" => $state && isset($ac->text->actions) ? $ac->text->actions : esc_html__('Actions', 'flx-forms'),

            /* translators: Content = column header for message content preview */
            "content" => $state && isset($ac->text->content) ? $ac->text->content : esc_html__('Content', 'flx-forms'),

            /* translators: Saving message */
            "saving" => $state && isset($ac->text->saving) ? $ac->text->saving : esc_html__('Saving...', 'flx-forms'),

            /* translators: No API connections message */
            "no_api_connections" => $state && isset($ac->text->no_api_connections) ? $ac->text->no_api_connections : esc_html__('No API connections yet. Click "Add New API Connection" to create one.', 'flx-forms'),

            /* translators: Confirm delete message */
            "confirm_delete" => $state && isset($ac->text->confirm_delete) ? $ac->text->confirm_delete : esc_html__('Are you sure you want to delete', 'flx-forms'),

            /* translators: Security check failed = error message */
            "security_check_failed" => $state && isset($ac->text->security_check_failed) ? $ac->text->security_check_failed : esc_html__('Security check failed', 'flx-forms'),

            /* translators: Permission denied = error message */
            "permission_denied" => $state && isset($ac->text->permission_denied) ? $ac->text->permission_denied : esc_html__('Permission denied', 'flx-forms'),

            /* translators: Connection name required = error message */
            "connection_name_required" => $state && isset($ac->text->connection_name_required) ? $ac->text->connection_name_required : esc_html__('Connection name is required', 'flx-forms'),

            /* translators: API endpoint required = error message */
            "api_endpoint_required" => $state && isset($ac->text->api_endpoint_required) ? $ac->text->api_endpoint_required : esc_html__('API endpoint URL is required', 'flx-forms'),

            /* translators: Connection ID required = error message */
            "connection_id_required" => $state && isset($ac->text->connection_id_required) ? $ac->text->connection_id_required : esc_html__('Connection ID is required', 'flx-forms'),

            /* translators: Connection not found = error message */
            "connection_not_found" => $state && isset($ac->text->connection_not_found) ? $ac->text->connection_not_found : esc_html__('Connection not found', 'flx-forms'),

            /* translators: API connection not found = error message */
            "api_connection_not_found" => $state && isset($ac->text->api_connection_not_found) ? $ac->text->api_connection_not_found : esc_html__('API connection not found or disabled', 'flx-forms'),

            /* translators: Rate limit exceeded = error message */
            "rate_limit_exceeded" => $state && isset($ac->text->rate_limit_exceeded) ? $ac->text->rate_limit_exceeded : esc_html__('Rate limit exceeded. Please try again later.', 'flx-forms'),

            /* translators: Invalid request format = error message */
            "invalid_request_format" => $state && isset($ac->text->invalid_request_format) ? $ac->text->invalid_request_format : esc_html__('Invalid request format', 'flx-forms'),

            /* translators: Failed to parse API response = error message */
            "parse_error" => $state && isset($ac->text->parse_error) ? $ac->text->parse_error : esc_html__('Failed to parse API response', 'flx-forms'),

            /* translators: API connection saved = success message */
            "api_connection_saved" => $state && isset($ac->text->api_connection_saved) ? $ac->text->api_connection_saved : esc_html__('API connection saved successfully', 'flx-forms'),

            /* translators: Connection test successful = success message */
            "connection_test_successful" => $state && isset($ac->text->connection_test_successful) ? $ac->text->connection_test_successful : esc_html__('Connection test successful', 'flx-forms'),

            /* translators: API connection deleted = success message */
            "api_connection_deleted" => $state && isset($ac->text->api_connection_deleted) ? $ac->text->api_connection_deleted : esc_html__('API connection deleted successfully', 'flx-forms'),

            /* translators: Connection enabled = status message */
            "connection_enabled" => $state && isset($ac->text->connection_enabled) ? $ac->text->connection_enabled : esc_html__('Connection enabled', 'flx-forms'),

            /* translators: Connection disabled = status message */
            "connection_disabled" => $state && isset($ac->text->connection_disabled) ? $ac->text->connection_disabled : esc_html__('Connection disabled', 'flx-forms'),

            /* translators: API connection ID required = error message */
            "api_connection_id_required" => $state && isset($ac->text->api_connection_id_required) ? $ac->text->api_connection_id_required : esc_html__('API connection ID is required', 'flx-forms'),

            /* translators: Form ID required = error message */
            "form_id_required" => $state && isset($ac->text->form_id_required) ? $ac->text->form_id_required : esc_html__('Form ID is required', 'flx-forms'),

            /* translators: Form not found = error message */
            "form_not_found" => $state && isset($ac->text->form_not_found) ? $ac->text->form_not_found : esc_html__('Form not found', 'flx-forms'),

            /* translators: Invalid form structure = error message */
            "invalid_form_structure" => $state && isset($ac->text->invalid_form_structure) ? $ac->text->invalid_form_structure : esc_html__('Invalid form structure', 'flx-forms'),

            /* translators: Invalid form ID = error message */
            "invalid_form_id" => $state && isset($ac->text->invalid_form_id) ? $ac->text->invalid_form_id : esc_html__('Invalid form ID', 'flx-forms'),

            /* translators: No matching data found = message */
            "no_matching_data" => $state && isset($ac->text->no_matching_data) ? $ac->text->no_matching_data : esc_html__('No matching data found', 'flx-forms'),

            /* translators: API returned error = error message with status code */
            "api_returned_error" => $state && isset($ac->text->api_returned_error) ? $ac->text->api_returned_error : esc_html__('API returned error: %d', 'flx-forms'),

            /* translators: Failed to update form = error message */
            "failed_to_update_form" => $state && isset($ac->text->failed_to_update_form) ? $ac->text->failed_to_update_form : esc_html__('Failed to update form', 'flx-forms'),

            /* translators: Form updated successfully = success message */
            "form_updated_successfully" => $state && isset($ac->text->form_updated_successfully) ? $ac->text->form_updated_successfully : esc_html__('Form updated successfully', 'flx-forms'),

            /* translators: Test = button text */
            "test" => $state && isset($ac->text->test) ? $ac->text->test : esc_html__('Test', 'flx-forms'),

            /* translators: camelCase alias of external_api, used by the API connections admin script */
            "externalApi" => $state && isset($ac->text->external_api) ? $ac->text->external_api : esc_html__('External API Connections', 'flx-forms'),
            /* translators: camelCase alias of add_new_api */
            "addNewApi" => $state && isset($ac->text->add_new_api) ? $ac->text->add_new_api : esc_html__('Add New API Connection', 'flx-forms'),
            /* translators: camelCase alias of edit_api_connection */
            "editApiConnection" => $state && isset($ac->text->edit_api_connection) ? $ac->text->edit_api_connection : esc_html__('Edit API Connection', 'flx-forms'),
            /* translators: camelCase alias of connection_name */
            "connectionName" => $state && isset($ac->text->connection_name) ? $ac->text->connection_name : esc_html__('Connection Name', 'flx-forms'),
            /* translators: camelCase alias of connection_name_placeholder */
            "connectionNamePlaceholder" => $state && isset($ac->text->connection_name_placeholder) ? $ac->text->connection_name_placeholder : esc_html__('e.g., Customer Lookup API', 'flx-forms'),
            /* translators: camelCase alias of http_method */
            "httpMethod" => $state && isset($ac->text->http_method) ? $ac->text->http_method : esc_html__('HTTP Method', 'flx-forms'),
            /* translators: camelCase alias of endpoint_url */
            "endpointUrl" => $state && isset($ac->text->endpoint_url) ? $ac->text->endpoint_url : esc_html__('API Endpoint URL', 'flx-forms'),
            /* translators: camelCase alias of endpoint_url_help */
            "endpointUrlHelp" => $state && isset($ac->text->endpoint_url_help) ? $ac->text->endpoint_url_help : esc_html__('Use {{field_id}} placeholders for dynamic values from form fields', 'flx-forms'),
            /* translators: camelCase alias of auth_type */
            "authType" => $state && isset($ac->text->auth_type) ? $ac->text->auth_type : esc_html__('Authentication Type', 'flx-forms'),
            /* translators: camelCase alias of no_auth */
            "noAuth" => $state && isset($ac->text->no_auth) ? $ac->text->no_auth : esc_html__('No Authentication', 'flx-forms'),
            /* translators: camelCase alias of custom_header */
            "customHeader" => $state && isset($ac->text->custom_header) ? $ac->text->custom_header : esc_html__('Custom Header', 'flx-forms'),
            /* translators: camelCase alias of auth_value */
            "authValue" => $state && isset($ac->text->auth_value) ? $ac->text->auth_value : esc_html__('Authentication Value', 'flx-forms'),
            /* translators: camelCase alias of auth_value_placeholder */
            "authValuePlaceholder" => $state && isset($ac->text->auth_value_placeholder) ? $ac->text->auth_value_placeholder : esc_html__('Enter token or credentials', 'flx-forms'),
            /* translators: camelCase alias of bearer_help */
            "bearerHelp" => $state && isset($ac->text->bearer_help) ? $ac->text->bearer_help : esc_html__('Enter your Bearer token without "Bearer " prefix', 'flx-forms'),
            /* translators: camelCase alias of basic_help */
            "basicHelp" => $state && isset($ac->text->basic_help) ? $ac->text->basic_help : esc_html__('Enter as username:password', 'flx-forms'),
            /* translators: camelCase alias of api_key_help */
            "apiKeyHelp" => $state && isset($ac->text->api_key_help) ? $ac->text->api_key_help : esc_html__('Enter your API key', 'flx-forms'),
            /* translators: camelCase alias of custom_auth_help */
            "customAuthHelp" => $state && isset($ac->text->custom_auth_help) ? $ac->text->custom_auth_help : esc_html__('Enter as Header-Name: value', 'flx-forms'),
            /* translators: camelCase alias of custom_headers */
            "customHeaders" => $state && isset($ac->text->custom_headers) ? $ac->text->custom_headers : esc_html__('Custom Headers', 'flx-forms'),
            /* translators: camelCase alias of no_custom_headers */
            "noCustomHeaders" => $state && isset($ac->text->no_custom_headers) ? $ac->text->no_custom_headers : esc_html__('No custom headers', 'flx-forms'),
            /* translators: camelCase alias of query_params */
            "queryParams" => $state && isset($ac->text->query_params) ? $ac->text->query_params : esc_html__('Query Parameters', 'flx-forms'),
            /* translators: camelCase alias of no_query_params */
            "noQueryParams" => $state && isset($ac->text->no_query_params) ? $ac->text->no_query_params : esc_html__('No query parameters', 'flx-forms'),
            /* translators: camelCase alias of request_body */
            "requestBody" => $state && isset($ac->text->request_body) ? $ac->text->request_body : esc_html__('Request Body Template (JSON)', 'flx-forms'),
            /* translators: camelCase alias of body_template_help */
            "bodyTemplateHelp" => $state && isset($ac->text->body_template_help) ? $ac->text->body_template_help : esc_html__('JSON template for POST/PUT/PATCH requests. Use {{field_id}} for dynamic values.', 'flx-forms'),
            /* translators: camelCase alias of response_mapping */
            "responseMapping" => $state && isset($ac->text->response_mapping) ? $ac->text->response_mapping : esc_html__('Response Mapping', 'flx-forms'),
            /* translators: camelCase alias of response_path */
            "responsePath" => $state && isset($ac->text->response_path) ? $ac->text->response_path : esc_html__('Response Data Path', 'flx-forms'),
            /* translators: camelCase alias of response_path_help */
            "responsePathHelp" => $state && isset($ac->text->response_path_help) ? $ac->text->response_path_help : esc_html__('Dot notation path to the data array in response. e.g., data.results or items', 'flx-forms'),
            /* translators: camelCase alias of field_mappings */
            "fieldMappings" => $state && isset($ac->text->field_mappings) ? $ac->text->field_mappings : esc_html__('Field Mappings', 'flx-forms'),
            /* translators: camelCase alias of no_field_mappings */
            "noFieldMappings" => $state && isset($ac->text->no_field_mappings) ? $ac->text->no_field_mappings : esc_html__('No field mappings', 'flx-forms'),
            /* translators: camelCase alias of field_mappings_help */
            "fieldMappingsHelp" => $state && isset($ac->text->field_mappings_help) ? $ac->text->field_mappings_help : esc_html__('Map API response fields to form field IDs', 'flx-forms'),
            /* translators: camelCase alias of cache_settings */
            "cacheSettings" => $state && isset($ac->text->cache_settings) ? $ac->text->cache_settings : esc_html__('Cache Settings', 'flx-forms'),
            /* translators: camelCase alias of cache_duration */
            "cacheDuration" => $state && isset($ac->text->cache_duration) ? $ac->text->cache_duration : esc_html__('Cache Duration (minutes)', 'flx-forms'),
            /* translators: camelCase alias of cache_duration_help */
            "cacheDurationHelp" => $state && isset($ac->text->cache_duration_help) ? $ac->text->cache_duration_help : esc_html__('0 = no caching', 'flx-forms'),
            /* translators: camelCase alias of basic_info */
            "basicInfo" => $state && isset($ac->text->basic_info) ? $ac->text->basic_info : esc_html__('Basic Information', 'flx-forms'),
            /* translators: camelCase alias of test_connection */
            "testConnection" => $state && isset($ac->text->test_connection) ? $ac->text->test_connection : esc_html__('Test Connection', 'flx-forms'),
            /* translators: camelCase alias of test_result */
            "testResult" => $state && isset($ac->text->test_result) ? $ac->text->test_result : esc_html__('Test Result', 'flx-forms'),
            /* translators: camelCase alias of testing */
            "testing" => $state && isset($ac->text->testing) ? $ac->text->testing : esc_html__('Testing connection...', 'flx-forms'),
            /* translators: camelCase alias of no_api_connections */
            "noApiConnections" => $state && isset($ac->text->no_api_connections) ? $ac->text->no_api_connections : esc_html__('No API connections yet. Click "Add New API Connection" to create one.', 'flx-forms'),
            /* translators: camelCase alias of confirm_delete */
            "confirmDelete" => $state && isset($ac->text->confirm_delete) ? $ac->text->confirm_delete : esc_html__('Are you sure you want to delete', 'flx-forms'),
            /* translators: camelCase alias of saving */
            "saving" => $state && isset($ac->text->saving) ? $ac->text->saving : esc_html__('Saving...', 'flx-forms'),

            /* translators: Prompt shown when no API connections exist yet */
            "clickToAdd" => $state && isset($ac->text->click_to_add) ? $ac->text->click_to_add : esc_html__('Click the button above to create your first API connection', 'flx-forms'),

            /* translators: Wizard step 1 label */
            "step1Label" => $state && isset($ac->text->step1_label) ? $ac->text->step1_label : esc_html__('Basic Information', 'flx-forms'),
            /* translators: Wizard step 2 label */
            "step2Label" => $state && isset($ac->text->step2_label) ? $ac->text->step2_label : esc_html__('Authentication', 'flx-forms'),
            /* translators: Wizard step 3 label */
            "step3Label" => $state && isset($ac->text->step3_label) ? $ac->text->step3_label : esc_html__('Field Mapping', 'flx-forms'),
            /* translators: Wizard step 4 label */
            "step4Label" => $state && isset($ac->text->step4_label) ? $ac->text->step4_label : esc_html__('Test & Save', 'flx-forms'),

            /* translators: Wizard step 1 card title */
            "basicInfoTitle" => $state && isset($ac->text->basic_info_title) ? $ac->text->basic_info_title : esc_html__('Basic API Information', 'flx-forms'),
            /* translators: Help text under the Connection Name field */
            "connectionNameHelp" => $state && isset($ac->text->connection_name_help) ? $ac->text->connection_name_help : esc_html__('A friendly name to identify this API connection', 'flx-forms'),
            /* translators: Description of the GET HTTP method option */
            "getMethodDesc" => $state && isset($ac->text->get_method_desc) ? $ac->text->get_method_desc : esc_html__('Fetch data', 'flx-forms'),
            /* translators: Description of the POST HTTP method option */
            "postMethodDesc" => $state && isset($ac->text->post_method_desc) ? $ac->text->post_method_desc : esc_html__('Send data', 'flx-forms'),
            /* translators: Help text under the Request Body field */
            "requestBodyHelp" => $state && isset($ac->text->request_body_help) ? $ac->text->request_body_help : esc_html__('JSON body for POST requests. Use {{field_id}} for dynamic values.', 'flx-forms'),

            /* translators: Wizard step 2 card title */
            "authTitle" => $state && isset($ac->text->auth_title) ? $ac->text->auth_title : esc_html__('Authentication Settings', 'flx-forms'),
            /* translators: Explanatory text above the authentication type selector */
            "authInfo" => $state && isset($ac->text->auth_info) ? $ac->text->auth_info : esc_html__('If your API requires authentication, select the type below. Otherwise, leave it as "No Authentication".', 'flx-forms'),
            /* translators: Description of the "No Authentication" option */
            "noAuthDesc" => $state && isset($ac->text->no_auth_desc) ? $ac->text->no_auth_desc : esc_html__('API is public', 'flx-forms'),
            /* translators: Description of the Bearer Token authentication option */
            "bearerDesc" => $state && isset($ac->text->bearer_desc) ? $ac->text->bearer_desc : esc_html__('JWT or OAuth tokens', 'flx-forms'),
            /* translators: Description of the API Key authentication option */
            "apiKeyDesc" => $state && isset($ac->text->api_key_desc) ? $ac->text->api_key_desc : esc_html__('X-API-Key header', 'flx-forms'),
            /* translators: Description of the Basic Auth authentication option */
            "basicDesc" => $state && isset($ac->text->basic_desc) ? $ac->text->basic_desc : esc_html__('Username:Password', 'flx-forms'),
            /* translators: Button that adds a new custom header row */
            "addHeader" => $state && isset($ac->text->add_header) ? $ac->text->add_header : esc_html__('Add Header', 'flx-forms'),
            /* translators: Empty state for the custom headers list */
            "noHeaders" => $state && isset($ac->text->no_headers) ? $ac->text->no_headers : esc_html__('No custom headers', 'flx-forms'),
            /* translators: Optional = marks a field as not required */
            "optional" => $state && isset($ac->text->optional) ? $ac->text->optional : esc_html__('Optional', 'flx-forms'),

            /* translators: Wizard step 3 card title */
            "fieldMappingTitle" => $state && isset($ac->text->field_mapping_title) ? $ac->text->field_mapping_title : esc_html__('Field Mapping', 'flx-forms'),
            /* translators: Form selector section title */
            "selectFormTitle" => $state && isset($ac->text->select_form_title) ? $ac->text->select_form_title : esc_html__('Select Form', 'flx-forms'),
            /* translators: Help text under the target form selector */
            "targetFormHelp" => $state && isset($ac->text->target_form_help) ? $ac->text->target_form_help : esc_html__('Select the form that will receive data from the API', 'flx-forms'),
            /* translators: Target Form = field label */
            "targetForm" => $state && isset($ac->text->target_form) ? $ac->text->target_form : esc_html__('Target Form', 'flx-forms'),
            /* translators: Form select placeholder */
            "selectForm" => $state && isset($ac->text->select_form) ? $ac->text->select_form : esc_html__('— Select a Form —', 'flx-forms'),
            /* translators: Section title for fields that trigger the API search */
            "searchFieldsTitle" => $state && isset($ac->text->search_fields_title) ? $ac->text->search_fields_title : esc_html__('Search Fields (Trigger Fields)', 'flx-forms'),
            /* translators: Description of the trigger-fields configuration */
            "searchConfigDesc" => $state && isset($ac->text->search_config_desc) ? $ac->text->search_config_desc : esc_html__('Select fields that trigger the API search', 'flx-forms'),
            /* translators: Form Field = column header */
            "formField" => $state && isset($ac->text->form_field) ? $ac->text->form_field : esc_html__('Form Field', 'flx-forms'),
            /* translators: API Parameter = column header */
            "apiParamName" => $state && isset($ac->text->api_param_name) ? $ac->text->api_param_name : esc_html__('API Parameter', 'flx-forms'),
            /* translators: Type = column header for the field's data type */
            "fieldType" => $state && isset($ac->text->field_type) ? $ac->text->field_type : esc_html__('Type', 'flx-forms'),
            /* translators: Validation message shown before a form is chosen */
            "selectFormFirst" => $state && isset($ac->text->select_form_first) ? $ac->text->select_form_first : esc_html__('Please select a form first', 'flx-forms'),
            /* translators: Help text explaining the API Parameter field, with a query-string example */
            "searchParamHelp" => $state && isset($ac->text->search_param_help) ? $ac->text->search_param_help : esc_html__('API Parameter is what will be sent to the API (e.g., "q" for ?q=value)', 'flx-forms'),
            /* translators: Section title for fields that get auto-populated from the API response */
            "targetFieldsTitle" => $state && isset($ac->text->target_fields_title) ? $ac->text->target_fields_title : esc_html__('Target Fields (Auto-Populate)', 'flx-forms'),
            /* translators: Description of the target-fields mapping */
            "targetFieldsInfo" => $state && isset($ac->text->target_fields_info) ? $ac->text->target_fields_info : esc_html__('Map API response to form fields', 'flx-forms'),
            /* translators: API Response Field = column header */
            "apiFieldName" => $state && isset($ac->text->api_field_name) ? $ac->text->api_field_name : esc_html__('API Response Field', 'flx-forms'),
            /* translators: Form Field to Fill = column header */
            "formFieldSelect" => $state && isset($ac->text->form_field_select) ? $ac->text->form_field_select : esc_html__('Form Field to Fill', 'flx-forms'),
            /* translators: Button that adds a new field mapping row */
            "addMapping" => $state && isset($ac->text->add_mapping) ? $ac->text->add_mapping : esc_html__('Add Mapping', 'flx-forms'),
            /* translators: Field select placeholder */
            "selectField" => $state && isset($ac->text->select_field) ? $ac->text->select_field : esc_html__('— Select Field —', 'flx-forms'),
            /* translators: Suffix shown after a count, e.g. "3 selected" */
            "fieldsSelected" => $state && isset($ac->text->fields_selected) ? $ac->text->fields_selected : esc_html__('selected', 'flx-forms'),

            /* translators: Help text above the cache duration field */
            "cacheHelp" => $state && isset($ac->text->cache_help) ? $ac->text->cache_help : esc_html__('Cache API responses to improve performance', 'flx-forms'),
            /* translators: Cache duration option meaning caching is turned off */
            "noCache" => $state && isset($ac->text->no_cache) ? $ac->text->no_cache : esc_html__('No caching', 'flx-forms'),
            /* translators: minutes = unit label for cache duration */
            "minutes" => $state && isset($ac->text->minutes) ? $ac->text->minutes : esc_html__('minutes', 'flx-forms'),
            /* translators: hour = unit label for cache duration */
            "hour" => $state && isset($ac->text->hour) ? $ac->text->hour : esc_html__('hour', 'flx-forms'),

            /* translators: Wizard step 4 card title */
            "testSaveTitle" => $state && isset($ac->text->test_save_title) ? $ac->text->test_save_title : esc_html__('Test Connection & Save', 'flx-forms'),
            /* translators: Summary card title before saving the connection */
            "connectionSummary" => $state && isset($ac->text->connection_summary) ? $ac->text->connection_summary : esc_html__('Connection Summary', 'flx-forms'),
            /* translators: Name = generic column/summary label */
            "name" => $state && isset($ac->text->name) ? $ac->text->name : esc_html__('Name', 'flx-forms'),
            /* translators: Method = HTTP method summary label */
            "method" => $state && isset($ac->text->method) ? $ac->text->method : esc_html__('Method', 'flx-forms'),
            /* translators: Endpoint = summary label for the API URL */
            "endpoint" => $state && isset($ac->text->endpoint) ? $ac->text->endpoint : esc_html__('Endpoint', 'flx-forms'),
            /* translators: Authentication = summary label */
            "auth" => $state && isset($ac->text->auth) ? $ac->text->auth : esc_html__('Authentication', 'flx-forms'),
            /* translators: Run Test = button that calls the API once to verify it works */
            "runTest" => $state && isset($ac->text->run_test) ? $ac->text->run_test : esc_html__('Run Test', 'flx-forms'),
            /* translators: Prompt shown before the connection has been tested */
            "clickTestBtn" => $state && isset($ac->text->click_test_btn) ? $ac->text->click_test_btn : esc_html__('Click "Run Test" to test your API connection', 'flx-forms'),
            /* translators: Toggle label to activate the connection once saved */
            "enableConnection" => $state && isset($ac->text->enable_connection) ? $ac->text->enable_connection : esc_html__('Enable this connection', 'flx-forms'),
            /* translators: Previous = wizard navigation button */
            "previous" => $state && isset($ac->text->previous) ? $ac->text->previous : esc_html__('Previous', 'flx-forms'),
            /* translators: Next = wizard navigation button */
            "next" => $state && isset($ac->text->next) ? $ac->text->next : esc_html__('Next', 'flx-forms'),
            /* translators: Save = generic save button */
            "save" => $state && isset($ac->text->save) ? $ac->text->save : esc_html__('Save', 'flx-forms'),
            /* translators: Back = generic back button */
            "back" => $state && isset($ac->text->back) ? $ac->text->back : esc_html__('Back', 'flx-forms'),
            /* translators: Edit = generic edit button */
            "edit" => $state && isset($ac->text->edit) ? $ac->text->edit : esc_html__('Edit', 'flx-forms'),
            /* translators: Test = generic test button */
            "test" => $state && isset($ac->text->test) ? $ac->text->test : esc_html__('Test', 'flx-forms'),
            /* translators: Active = status shown for an enabled API connection */
            "enabled" => $state && isset($ac->text->enabled) ? $ac->text->enabled : esc_html__('Active', 'flx-forms'),
            /* translators: Inactive = status shown for a disabled API connection */
            "disabled" => $state && isset($ac->text->disabled) ? $ac->text->disabled : esc_html__('Inactive', 'flx-forms'),

            /* translators: Validation error when the connection name field is empty */
            "nameRequired" => $state && isset($ac->text->name_required) ? $ac->text->name_required : esc_html__('Please enter a connection name', 'flx-forms'),
            /* translators: Validation error when the endpoint URL field is empty */
            "urlRequired" => $state && isset($ac->text->url_required) ? $ac->text->url_required : esc_html__('Please enter an API endpoint URL', 'flx-forms'),
            /* translators: Validation error when the endpoint URL is not http(s) */
            "invalidUrl" => $state && isset($ac->text->invalid_url) ? $ac->text->invalid_url : esc_html__('Please enter a valid URL starting with http:// or https://', 'flx-forms'),
            /* translators: Generic validation error when required wizard fields are missing */
            "fillRequired" => $state && isset($ac->text->fill_required) ? $ac->text->fill_required : esc_html__('Please fill all required fields', 'flx-forms'),
            /* translators: Generic loading status */
            "loading" => $state && isset($ac->text->loading) ? $ac->text->loading : esc_html__('Loading...', 'flx-forms'),

            /* translators: Success message after saving an API connection */
            "savedSuccess" => $state && isset($ac->text->saved_success) ? $ac->text->saved_success : esc_html__('Connection saved successfully!', 'flx-forms'),
            /* translators: Error message when saving an API connection fails */
            "saveFailed" => $state && isset($ac->text->save_failed) ? $ac->text->save_failed : esc_html__('Failed to save connection', 'flx-forms'),
            /* translators: Generic error message */
            "errorOccurred" => $state && isset($ac->text->error_occurred) ? $ac->text->error_occurred : esc_html__('An error occurred', 'flx-forms'),
            /* translators: Success message after testing an API connection */
            "connectionSuccess" => $state && isset($ac->text->connection_success) ? $ac->text->connection_success : esc_html__('Connection successful!', 'flx-forms'),
            /* translators: Error message when testing an API connection fails */
            "connectionFailed" => $state && isset($ac->text->connection_failed) ? $ac->text->connection_failed : esc_html__('Connection failed', 'flx-forms'),
            /* translators: Error message when the connection test itself fails to run */
            "testFailed" => $state && isset($ac->text->test_failed) ? $ac->text->test_failed : esc_html__('Test failed. Please check the URL and try again.', 'flx-forms'),
            /* translators: Success message after deleting an API connection */
            "deleteSuccess" => $state && isset($ac->text->delete_success) ? $ac->text->delete_success : esc_html__('Connection deleted', 'flx-forms'),
            /* translators: Error shown when the form's data could not be loaded */
            "formNotFound" => $state && isset($ac->text->form_not_found) ? $ac->text->form_not_found : esc_html__('Form data not found. Please refresh the page.', 'flx-forms'),
            /* translators: Empty state when a form has no fields that can be auto-filled */
            "noFieldsFound" => $state && isset($ac->text->no_fields_found) ? $ac->text->no_fields_found : esc_html__('No fillable fields found in this form', 'flx-forms'),

            /* translators: Banner title shown on a form using the API auto-populate feature */
            "atfllApiActive" => $state && isset($ac->text->atfll_api_active) ? $ac->text->atfll_api_active : esc_html__('API Auto-Populate Integration is Active', 'flx-forms'),
            /* translators: Banner description pointing the admin to where the integration is configured */
            "atfllApiActiveDesc" => $state && isset($ac->text->atfll_api_active_desc) ? $ac->text->atfll_api_active_desc : esc_html__('This form uses External API Auto-Populate. To configure settings, go to', 'flx-forms'),
            /* translators: Link text pointing to the Auto-Populate Integrations page */
            "atfllApiLink" => $state && isset($ac->text->atfll_api_link) ? $ac->text->atfll_api_link : esc_html__('Auto-Populate Integrations', 'flx-forms'),
            /* translators: Badge shown on a field that is auto-filled via an external API */
            "atfllApiFieldBadge" => $state && isset($ac->text->atfll_api_field_badge) ? $ac->text->atfll_api_field_badge : esc_html__('Auto-filled via External API', 'flx-forms'),

            /* translators: Status = generic column header */
            "status" => $state && isset($ac->text->status) ? $ac->text->status : esc_html__('Status', 'flx-forms'),
            /* translators: Actions = generic column header */
            "actions" => $state && isset($ac->text->actions) ? $ac->text->actions : esc_html__('Actions', 'flx-forms'),
            /* translators: Enable = generic toggle action */
            "enable" => $state && isset($ac->text->enable) ? $ac->text->enable : esc_html__('Enable', 'flx-forms'),
            /* translators: Disable = generic toggle action */
            "disable" => $state && isset($ac->text->disable) ? $ac->text->disable : esc_html__('Disable', 'flx-forms'),
            /* translators: Delete = generic delete action */
            "delete" => $state && isset($ac->text->delete) ? $ac->text->delete : esc_html__('Delete', 'flx-forms'),
            /* translators: Cancel = generic cancel action */
            "cancel" => $state && isset($ac->text->cancel) ? $ac->text->cancel : esc_html__('Cancel', 'flx-forms'),
            /* translators: Authentication = generic column header */
            "authentication" => $state && isset($ac->text->authentication) ? $ac->text->authentication : esc_html__('Authentication', 'flx-forms'),
            /* translators: Server-side validation error when the endpoint URL is missing */
            "endpointRequired" => $state && isset($ac->text->endpoint_required) ? $ac->text->endpoint_required : esc_html__('API endpoint URL is required', 'flx-forms'),
        ];
    }

    public static function get_payment_phrases($ac = null, $state = false) {
        return [

            /* translators: %s = form title. Shown as the PayPal tab label for a specific form */
            "paypals" => $state && isset($ac->text->paypals) ? $ac->text->paypals : esc_html__('PayPal %s', 'flx-forms'),

            /* translators: %s = form title. Shown as the Stripe tab label for a specific form */
            "stripes" => $state && isset($ac->text->stripes) ? $ac->text->stripes : esc_html__('Stripe %s', 'flx-forms'),

            /* translators: Payments = admin page title */
            "pay_payments" => $state && isset($ac->text->pay_payments) ? $ac->text->pay_payments : esc_html__('Payments', 'flx-forms'),

            /* translators: Search = search input placeholder */
            "pay_search" => $state && isset($ac->text->pay_search) ? $ac->text->pay_search : esc_html__('Search', 'flx-forms'),

            /* translators: You do not have permission to access this page */
            "pay_no_permission" => $state && isset($ac->text->pay_no_permission) ? $ac->text->pay_no_permission : esc_html__('You do not have permission to access this page.', 'flx-forms'),

            /* translators: All Statuses = filter dropdown */
            "pay_allStatuses" => $state && isset($ac->text->pay_allStatuses) ? $ac->text->pay_allStatuses : esc_html__('All Statuses', 'flx-forms'),

            /* translators: All Forms = filter dropdown */
            "pay_allForms" => $state && isset($ac->text->pay_allForms) ? $ac->text->pay_allForms : esc_html__('All Forms', 'flx-forms'),

            /* translators: Active = payment status */
            "pay_active" => $state && isset($ac->text->pay_active) ? $ac->text->pay_active : esc_html__('Active', 'flx-forms'),

            /* translators: Completed = payment status */
            "pay_completed" => $state && isset($ac->text->pay_completed) ? $ac->text->pay_completed : esc_html__('Completed', 'flx-forms'),

            /* translators: Pending = payment status */
            "pay_pending" => $state && isset($ac->text->pay_pending) ? $ac->text->pay_pending : esc_html__('Pending', 'flx-forms'),

            /* translators: Cancelled = payment status */
            "pay_cancelled" => $state && isset($ac->text->pay_cancelled) ? $ac->text->pay_cancelled : esc_html__('Cancelled', 'flx-forms'),

            /* translators: Refunded = payment status */
            "pay_refunded" => $state && isset($ac->text->pay_refunded) ? $ac->text->pay_refunded : esc_html__('Refunded', 'flx-forms'),

            /* translators: Failed = payment status */
            "pay_failed" => $state && isset($ac->text->pay_failed) ? $ac->text->pay_failed : esc_html__('Failed', 'flx-forms'),


            /* translators: No payments found = empty table message */
            "pay_noPayments" => $state && isset($ac->text->pay_noPayments) ? $ac->text->pay_noPayments : esc_html__('No payments found.', 'flx-forms'),

            /* translators: Loading... = loading indicator */
            "pay_loading" => $state && isset($ac->text->pay_loading) ? $ac->text->pay_loading : esc_html__('Loading...', 'flx-forms'),

            /* translators: View Details = action button */
            "pay_viewDetails" => $state && isset($ac->text->pay_viewDetails) ? $ac->text->pay_viewDetails : esc_html__('View Details', 'flx-forms'),

            /* translators: Refund = action button */
            "pay_refund" => $state && isset($ac->text->pay_refund) ? $ac->text->pay_refund : esc_html__('Refund', 'flx-forms'),

            /* translators: Cancel Subscription = action button */
            "pay_cancelSub" => $state && isset($ac->text->pay_cancelSub) ? $ac->text->pay_cancelSub : esc_html__('Cancel Subscription', 'flx-forms'),

            /* translators: Suspend Subscription = action button */
            "pay_suspendSub" => $state && isset($ac->text->pay_suspendSub) ? $ac->text->pay_suspendSub : esc_html__('Suspend Subscription', 'flx-forms'),

            /* translators: Reactivate Subscription = action button */
            "pay_reactivateSub" => $state && isset($ac->text->pay_reactivateSub) ? $ac->text->pay_reactivateSub : esc_html__('Reactivate Subscription', 'flx-forms'),

            /* translators: Are you sure you want to refund this payment? = confirm dialog */
            "pay_confirmRefund" => $state && isset($ac->text->pay_confirmRefund) ? $ac->text->pay_confirmRefund : esc_html__('Are you sure you want to refund this payment?', 'flx-forms'),

            /* translators: Are you sure you want to cancel this subscription? = confirm dialog */
            "pay_confirmCancel" => $state && isset($ac->text->pay_confirmCancel) ? $ac->text->pay_confirmCancel : esc_html__('Are you sure you want to cancel this subscription?', 'flx-forms'),

            /* translators: Are you sure you want to suspend this subscription? = confirm dialog */
            "pay_confirmSuspend" => $state && isset($ac->text->pay_confirmSuspend) ? $ac->text->pay_confirmSuspend : esc_html__('Are you sure you want to suspend this subscription?', 'flx-forms'),

            /* translators: Are you sure you want to reactivate this subscription? = confirm dialog */
            "pay_confirmReactivate" => $state && isset($ac->text->pay_confirmReactivate) ? $ac->text->pay_confirmReactivate : esc_html__('Are you sure you want to reactivate this subscription?', 'flx-forms'),

            /* translators: Success = success message */
            "pay_success" => $state && isset($ac->text->pay_success) ? $ac->text->pay_success : esc_html__('Success', 'flx-forms'),

            /* translators: Error = error message */
            "pay_error" => $state && isset($ac->text->pay_error) ? $ac->text->pay_error : esc_html__('Error', 'flx-forms'),

            /* translators: Close = close button */
            "pay_close" => $state && isset($ac->text->pay_close) ? $ac->text->pay_close : esc_html__('Close', 'flx-forms'),

            /* translators: Payment refunded successfully = success toast */
            "pay_refundSuccess" => $state && isset($ac->text->pay_refundSuccess) ? $ac->text->pay_refundSuccess : esc_html__('Payment refunded successfully.', 'flx-forms'),

            /* translators: Subscription cancelled successfully = success toast */
            "pay_cancelSuccess" => $state && isset($ac->text->pay_cancelSuccess) ? $ac->text->pay_cancelSuccess : esc_html__('Subscription cancelled successfully.', 'flx-forms'),

            /* translators: Copied = clipboard copy toast */
            "pay_copied" => $state && isset($ac->text->pay_copied) ? $ac->text->pay_copied : esc_html__('Copied', 'flx-forms'),

            /* translators: Transaction ID = field label */
            "pay_transactionId" => $state && isset($ac->text->pay_transactionId) ? $ac->text->pay_transactionId : esc_html__('Transaction ID', 'flx-forms'),

            /* translators: Subscription ID = field label */
            "pay_subscriptionId" => $state && isset($ac->text->pay_subscriptionId) ? $ac->text->pay_subscriptionId : esc_html__('Subscription ID', 'flx-forms'),

            /* translators: Plan ID = field label */
            "pay_planId" => $state && isset($ac->text->pay_planId) ? $ac->text->pay_planId : esc_html__('Plan ID', 'flx-forms'),

            /* translators: Capture ID = field label */
            "pay_captureId" => $state && isset($ac->text->pay_captureId) ? $ac->text->pay_captureId : esc_html__('Capture ID', 'flx-forms'),

            /* translators: Amount = field label */
            "pay_amount" => $state && isset($ac->text->pay_amount) ? $ac->text->pay_amount : esc_html__('Amount', 'flx-forms'),

            /* translators: Currency = field label */
            "pay_currency" => $state && isset($ac->text->pay_currency) ? $ac->text->pay_currency : esc_html__('Currency', 'flx-forms'),

            /* translators: Status = field label */
            "pay_status" => $state && isset($ac->text->pay_status) ? $ac->text->pay_status : esc_html__('Status', 'flx-forms'),

            /* translators: Payment Type = field label */
            "pay_paymentType" => $state && isset($ac->text->pay_paymentType) ? $ac->text->pay_paymentType : esc_html__('Payment Type', 'flx-forms'),

            /* translators: Interval = field label */
            "pay_interval" => $state && isset($ac->text->pay_interval) ? $ac->text->pay_interval : esc_html__('Interval', 'flx-forms'),

            /* translators: Payer Email = field label */
            "pay_payerEmail" => $state && isset($ac->text->pay_payerEmail) ? $ac->text->pay_payerEmail : esc_html__('Payer Email', 'flx-forms'),

            /* translators: Payer Name = field label */
            "pay_payerName" => $state && isset($ac->text->pay_payerName) ? $ac->text->pay_payerName : esc_html__('Payer Name', 'flx-forms'),

            /* translators: Form = column header */
            "pay_formName" => $state && isset($ac->text->pay_formName) ? $ac->text->pay_formName : esc_html__('Form', 'flx-forms'),

            /* translators: Date = column header */
            "pay_date" => $state && isset($ac->text->pay_date) ? $ac->text->pay_date : esc_html__('Date', 'flx-forms'),

            /* translators: Tracking Code = field label */
            "pay_trackCode" => $state && isset($ac->text->pay_trackCode) ? $ac->text->pay_trackCode : esc_html__('Tracking Code', 'flx-forms'),

            /* translators: User = column header */
            "pay_user" => $state && isset($ac->text->pay_user) ? $ac->text->pay_user : esc_html__('User', 'flx-forms'),

            /* translators: One-time = payment type label */
            "pay_oneTime" => $state && isset($ac->text->pay_oneTime) ? $ac->text->pay_oneTime : esc_html__('One-time', 'flx-forms'),

            /* translators: Subscription = payment type label */
            "pay_subscription" => $state && isset($ac->text->pay_subscription) ? $ac->text->pay_subscription : esc_html__('Subscription', 'flx-forms'),

            /* translators: Daily = interval label */
            "pay_daily" => $state && isset($ac->text->pay_daily) ? $ac->text->pay_daily : esc_html__('Daily', 'flx-forms'),

            /* translators: Weekly = interval label */
            "pay_weekly" => $state && isset($ac->text->pay_weekly) ? $ac->text->pay_weekly : esc_html__('Weekly', 'flx-forms'),

            /* translators: Monthly = interval label */
            "pay_monthly" => $state && isset($ac->text->pay_monthly) ? $ac->text->pay_monthly : esc_html__('Monthly', 'flx-forms'),

            /* translators: Yearly = interval label */
            "pay_yearly" => $state && isset($ac->text->pay_yearly) ? $ac->text->pay_yearly : esc_html__('Yearly', 'flx-forms'),

            /* translators: Payment Details = modal title */
            "pay_paymentDetails" => $state && isset($ac->text->pay_paymentDetails) ? $ac->text->pay_paymentDetails : esc_html__('Payment Details', 'flx-forms'),

            /* translators: Actions = column header */
            "pay_actions" => $state && isset($ac->text->pay_actions) ? $ac->text->pay_actions : esc_html__('Actions', 'flx-forms'),

            /* translators: Subscription Details = modal title */
            "pay_subscriptionDetail" => $state && isset($ac->text->pay_subscriptionDetail) ? $ac->text->pay_subscriptionDetail : esc_html__('Subscription Details', 'flx-forms'),

            /* translators: Next Billing Date = field label */
            "pay_nextBilling" => $state && isset($ac->text->pay_nextBilling) ? $ac->text->pay_nextBilling : esc_html__('Next Billing Date', 'flx-forms'),

            /* translators: Export = export button */
            "pay_export" => $state && isset($ac->text->pay_export) ? $ac->text->pay_export : esc_html__('Export', 'flx-forms'),

            /* translators: Total = total label */
            "pay_total" => $state && isset($ac->text->pay_total) ? $ac->text->pay_total : esc_html__('Total', 'flx-forms'),

            /* translators: of = pagination separator */
            "pay_of" => $state && isset($ac->text->pay_of) ? $ac->text->pay_of : esc_html__('of', 'flx-forms'),

            /* translators: Page = pagination label */
            "pay_page" => $state && isset($ac->text->pay_page) ? $ac->text->pay_page : esc_html__('Page', 'flx-forms'),

            /* translators: Previous = pagination button */
            "pay_previous" => $state && isset($ac->text->pay_previous) ? $ac->text->pay_previous : esc_html__('Previous', 'flx-forms'),

            /* translators: Next = pagination button */
            "pay_next" => $state && isset($ac->text->pay_next) ? $ac->text->pay_next : esc_html__('Next', 'flx-forms'),

            /* translators: Rows per page = pagination label */
            "pay_rowsPerPage" => $state && isset($ac->text->pay_rowsPerPage) ? $ac->text->pay_rowsPerPage : esc_html__('Rows per page', 'flx-forms'),

            /* translators: Suspended = status label */
            "pay_suspended" => $state && isset($ac->text->pay_suspended) ? $ac->text->pay_suspended : esc_html__('Suspended', 'flx-forms'),

            /* translators: Suspend Subscription = action button */
            "pay_suspendSub" => $state && isset($ac->text->pay_suspendSub) ? $ac->text->pay_suspendSub : esc_html__('Suspend Subscription', 'flx-forms'),

            /* translators: Reactivate Subscription = action button */
            "pay_reactivateSub" => $state && isset($ac->text->pay_reactivateSub) ? $ac->text->pay_reactivateSub : esc_html__('Reactivate Subscription', 'flx-forms'),

            /* translators: Are you sure you want to suspend this subscription? = confirm dialog */
            "pay_confirmSuspend" => $state && isset($ac->text->pay_confirmSuspend) ? $ac->text->pay_confirmSuspend : esc_html__('Are you sure you want to suspend this subscription?', 'flx-forms'),

            /* translators: Are you sure you want to reactivate this subscription? = confirm dialog */
            "pay_confirmReactivate" => $state && isset($ac->text->pay_confirmReactivate) ? $ac->text->pay_confirmReactivate : esc_html__('Are you sure you want to reactivate this subscription?', 'flx-forms'),

            /* translators: Subscription suspended successfully = success toast */
            "pay_suspendSuccess" => $state && isset($ac->text->pay_suspendSuccess) ? $ac->text->pay_suspendSuccess : esc_html__('Subscription suspended successfully.', 'flx-forms'),

            /* translators: Subscription reactivated successfully = success toast */
            "pay_reactivateSuccess" => $state && isset($ac->text->pay_reactivateSuccess) ? $ac->text->pay_reactivateSuccess : esc_html__('Subscription reactivated successfully.', 'flx-forms'),

            /* translators: Payment Management Dashboard = header subtitle */
            "pay_headerSubtitle" => $state && isset($ac->text->pay_headerSubtitle) ? $ac->text->pay_headerSubtitle : esc_html__('Payment Management Dashboard', 'flx-forms'),

            /* translators: %s = gateway name (PayPal/Stripe). Header description */
            "pay_headerDesc" => $state && isset($ac->text->pay_headerDesc) ? $ac->text->pay_headerDesc : esc_html__('Manage and track all %s transactions. View payment details, process refunds, and export records.', 'flx-forms'),

            /* translators: Secure = feature badge in header */
            "pay_secure" => $state && isset($ac->text->pay_secure) ? $ac->text->pay_secure : esc_html__('Secure', 'flx-forms'),

            /* translators: Download payment records = export hint in header */
            "pay_downloadRecords" => $state && isset($ac->text->pay_downloadRecords) ? $ac->text->pay_downloadRecords : esc_html__('Download payment records', 'flx-forms'),

            /* translators: Start = subscription start date label */
            "pay_start" => $state && isset($ac->text->pay_start) ? $ac->text->pay_start : esc_html__('Start', 'flx-forms'),

            /* translators: Last Payment = subscription last payment label */
            "pay_lastPayment" => $state && isset($ac->text->pay_lastPayment) ? $ac->text->pay_lastPayment : esc_html__('Last Payment', 'flx-forms'),

            /* translators: Copy = copy button tooltip */
            "pay_copy" => $state && isset($ac->text->pay_copy) ? $ac->text->pay_copy : esc_html__('Copy', 'flx-forms'),

            /* translators: Powered by = footer text */
            "pay_poweredBy" => $state && isset($ac->text->pay_poweredBy) ? $ac->text->pay_poweredBy : esc_html__('Powered by', 'flx-forms'),

            /* translators: and Bootstrap Icon. Created by = footer segment */
            "pay_createdBy" => $state && isset($ac->text->pay_createdBy) ? $ac->text->pay_createdBy : esc_html__('and Bootstrap Icon. Created by', 'flx-forms'),

            /* translators: Flux Forms = brand name */
            "pay_brandName" => $state && isset($ac->text->pay_brandName) ? $ac->text->pay_brandName : esc_html__('Flux Forms', 'flx-forms'),

            /* translators: Please Wait = loading overlay */
            "pay_pleaseWait" => $state && isset($ac->text->pay_pleaseWait) ? $ac->text->pay_pleaseWait : esc_html__('Please Wait', 'flx-forms'),
        ];
    }

    public static function get_webhook_phrases($ac = null, $state = false) {
        return [

        ];
    }

    /**
     * Google Sheet add-on phrases. Consumed by the Google Sheet admin pages
     * (settings + logs) and localized into the wizard JS as `efb_google_sheet.text`.
     * Every visible string in vendor/googlesheet lives here so it can be translated
     * from the central phrase editor. Strings with %s/%d are format templates the
     * JS fills in with String.replace().
     */
    public static function get_googlesheet_phrases($ac = null, $state = false) {
        return [

            /* ===== Header (settings page) ===== */
            /* translators: Google Sheet page main title (brand) */
            "gs_title" => $state && isset($ac->text->gs_title) ? $ac->text->gs_title : esc_html__('Google Sheet', 'flx-forms'),
            /* translators: Google Sheet page subtitle */
            "gs_subtitle" => $state && isset($ac->text->gs_subtitle) ? $ac->text->gs_subtitle : esc_html__('Sync Form Responses to Google Sheets', 'flx-forms'),
            /* translators: Google Sheet page intro description */
            "gs_headerDesc" => $state && isset($ac->text->gs_headerDesc) ? $ac->text->gs_headerDesc : esc_html__('Automatically send form submissions to your Google Sheets. Connect one or more service accounts, link a sheet to each form, and let data flow in real time — no webhook or browser login required.', 'flx-forms'),
            /* translators: Connection status badge — connected */
            "gs_connected" => $state && isset($ac->text->gs_connected) ? $ac->text->gs_connected : esc_html__('Connected', 'flx-forms'),
            /* translators: Connection status badge — not connected */
            "gs_notConnected" => $state && isset($ac->text->gs_notConnected) ? $ac->text->gs_notConnected : esc_html__('Not Connected', 'flx-forms'),
            /* translators: Feature badge — automatic sync */
            "gs_autoSync" => $state && isset($ac->text->gs_autoSync) ? $ac->text->gs_autoSync : esc_html__('Auto Sync', 'flx-forms'),
            /* translators: Feature badge — automatic header row */
            "gs_autoHeader" => $state && isset($ac->text->gs_autoHeader) ? $ac->text->gs_autoHeader : esc_html__('Auto Header', 'flx-forms'),
            /* translators: Navigation chip linking to the sync logs page */
            "gs_syncLogsChip" => $state && isset($ac->text->gs_syncLogsChip) ? $ac->text->gs_syncLogsChip : esc_html__('Sync Logs', 'flx-forms'),
            /* translators: Navigation chip linking back to the settings page */
            "gs_settingsChip" => $state && isset($ac->text->gs_settingsChip) ? $ac->text->gs_settingsChip : esc_html__('Google Sheet Settings', 'flx-forms'),
            /* translators: Header status card label */
            "gs_activeBindings" => $state && isset($ac->text->gs_activeBindings) ? $ac->text->gs_activeBindings : esc_html__('Active Bindings', 'flx-forms'),
            /* translators: Header status value — singular form count (paired with gs_formPlural via _n()) */
            "gs_formSingular" => $state && isset($ac->text->gs_formSingular) ? $ac->text->gs_formSingular : esc_html(_n('Form', 'Forms', 1, 'flx-forms')),
            /* translators: Header status value — plural form count (paired with gs_formSingular via _n()) */
            "gs_formPlural" => $state && isset($ac->text->gs_formPlural) ? $ac->text->gs_formPlural : esc_html(_n('Form', 'Forms', 2, 'flx-forms')),
            /* translators: %d = number of connected service accounts (singular, paired with gs_saConnectedPlural via _n()) */
            "gs_saConnected" => $state && isset($ac->text->gs_saConnected) ? $ac->text->gs_saConnected : esc_html(_n('%d service account connected', '%d service accounts connected', 1, 'flx-forms')),
            /* translators: %d = number of connected service accounts (plural, paired with gs_saConnected via _n()) */
            "gs_saConnectedPlural" => $state && isset($ac->text->gs_saConnectedPlural) ? $ac->text->gs_saConnectedPlural : esc_html(_n('%d service account connected', '%d service accounts connected', 2, 'flx-forms')),
            /* translators: Header hint when no service account is connected */
            "gs_noSaYet" => $state && isset($ac->text->gs_noSaYet) ? $ac->text->gs_noSaYet : esc_html__('No service account connected yet', 'flx-forms'),

            /* ===== Tabs ===== */
            /* translators: Tab title — service account connections */
            "gs_tabConnections" => $state && isset($ac->text->gs_tabConnections) ? $ac->text->gs_tabConnections : esc_html__('Connections', 'flx-forms'),
            /* translators: Tab title — form-to-sheet bindings */
            "gs_tabBindings" => $state && isset($ac->text->gs_tabBindings) ? $ac->text->gs_tabBindings : esc_html__('Form Bindings', 'flx-forms'),
            /* translators: Tab title — help and guide */
            "gs_tabHelp" => $state && isset($ac->text->gs_tabHelp) ? $ac->text->gs_tabHelp : esc_html__('Help & Guide', 'flx-forms'),

            /* ===== Common actions / status ===== */
            /* translators: Generic error message */
            "gs_error" => $state && isset($ac->text->gs_error) ? $ac->text->gs_error : esc_html__('Something went wrong.', 'flx-forms'),
            /* translators: Saved success message */
            "gs_saved" => $state && isset($ac->text->gs_saved) ? $ac->text->gs_saved : esc_html__('Saved successfully.', 'flx-forms'),
            /* translators: Save button */
            "gs_save" => $state && isset($ac->text->gs_save) ? $ac->text->gs_save : esc_html__('Save', 'flx-forms'),
            /* translators: Cancel button */
            "gs_cancel" => $state && isset($ac->text->gs_cancel) ? $ac->text->gs_cancel : esc_html__('Cancel', 'flx-forms'),
            /* translators: Edit button */
            "gs_edit" => $state && isset($ac->text->gs_edit) ? $ac->text->gs_edit : esc_html__('Edit', 'flx-forms'),
            /* translators: Delete button */
            "gs_delete" => $state && isset($ac->text->gs_delete) ? $ac->text->gs_delete : esc_html__('Delete', 'flx-forms'),
            /* translators: Remove button */
            "gs_remove" => $state && isset($ac->text->gs_remove) ? $ac->text->gs_remove : esc_html__('Remove', 'flx-forms'),
            /* translators: Back button */
            "gs_back" => $state && isset($ac->text->gs_back) ? $ac->text->gs_back : esc_html__('Back', 'flx-forms'),
            /* translators: Next button */
            "gs_next" => $state && isset($ac->text->gs_next) ? $ac->text->gs_next : esc_html__('Next', 'flx-forms'),
            /* translators: Open link label */
            "gs_open" => $state && isset($ac->text->gs_open) ? $ac->text->gs_open : esc_html__('Open', 'flx-forms'),
            /* translators: Open in Google Sheets link label */
            "gs_openInSheets" => $state && isset($ac->text->gs_openInSheets) ? $ac->text->gs_openInSheets : esc_html__('Open in Google Sheets', 'flx-forms'),
            /* translators: Test Connection button */
            "gs_testConnection" => $state && isset($ac->text->gs_testConnection) ? $ac->text->gs_testConnection : esc_html__('Test Connection', 'flx-forms'),

            /* ===== Connections tab ===== */
            /* translators: Section card title — service accounts */
            "gs_serviceAccounts" => $state && isset($ac->text->gs_serviceAccounts) ? $ac->text->gs_serviceAccounts : esc_html__('Service Accounts', 'flx-forms'),
            /* translators: Section card subtitle — service accounts */
            "gs_serviceAccountsSub" => $state && isset($ac->text->gs_serviceAccountsSub) ? $ac->text->gs_serviceAccountsSub : esc_html__('Connect one or more Google service accounts — no browser login required', 'flx-forms'),
            /* translators: OpenSSL missing banner — bold lead */
            "gs_opensslMissingLead" => $state && isset($ac->text->gs_opensslMissingLead) ? $ac->text->gs_opensslMissingLead : esc_html__('Your server is missing the PHP OpenSSL extension.', 'flx-forms'),
            /* translators: OpenSSL missing banner — body. %s = the literal "openssl" code tag */
            "gs_opensslMissingBody" => $state && isset($ac->text->gs_opensslMissingBody) ? $ac->text->gs_opensslMissingBody : esc_html__('Google Sheet sync cannot work until it is enabled. Please ask your host to enable the %s PHP extension, then reload this page.', 'flx-forms'),
            /* translators: Connection test status — not tested */
            "gs_notTested" => $state && isset($ac->text->gs_notTested) ? $ac->text->gs_notTested : esc_html__('Not tested', 'flx-forms'),
            /* translators: Connection test status — failed */
            "gs_failed" => $state && isset($ac->text->gs_failed) ? $ac->text->gs_failed : esc_html__('Failed', 'flx-forms'),
            /* translators: Default service account pill */
            "gs_default" => $state && isset($ac->text->gs_default) ? $ac->text->gs_default : esc_html__('Default', 'flx-forms'),
            /* translators: Copy-email button tooltip */
            "gs_copyThisEmail" => $state && isset($ac->text->gs_copyThisEmail) ? $ac->text->gs_copyThisEmail : esc_html__('Copy this email', 'flx-forms'),
            /* translators: Copy-email button tooltip (short) */
            "gs_copyEmail" => $state && isset($ac->text->gs_copyEmail) ? $ac->text->gs_copyEmail : esc_html__('Copy email', 'flx-forms'),
            /* translators: Per-connection sharing instructions (HTML allowed) */
            "gs_connShareHint" => $state && isset($ac->text->gs_connShareHint) ? $ac->text->gs_connShareHint : __('To sync an<strong>existing</strong>sheet: open it in Google Sheets →<strong>Share</strong>→ paste the email above → set role to<strong>Editor</strong>. To create a<strong>new</strong>sheet, just use Form Bindings — no sharing needed.', 'flx-forms'),
            /* translators: Set default service account button */
            "gs_setDefault" => $state && isset($ac->text->gs_setDefault) ? $ac->text->gs_setDefault : esc_html__('Set Default', 'flx-forms'),
            /* translators: Retest connection button */
            "gs_retest" => $state && isset($ac->text->gs_retest) ? $ac->text->gs_retest : esc_html__('Retest', 'flx-forms'),
            /* translators: Onboarding title (first run) */
            "gs_onboardTitle" => $state && isset($ac->text->gs_onboardTitle) ? $ac->text->gs_onboardTitle : esc_html__('Let’s connect Google Sheets — about 3 minutes, one time', 'flx-forms'),
            /* translators: Onboarding description (first run) */
            "gs_onboardDesc" => $state && isset($ac->text->gs_onboardDesc) ? $ac->text->gs_onboardDesc : esc_html__('Follow the numbered steps on the right to download a free service account key from Google, then drop the file below. You only do this once. After that, creating a sheet for any form is a couple of clicks.', 'flx-forms'),
            /* translators: Your Google email field label */
            "gs_yourGoogleEmail" => $state && isset($ac->text->gs_yourGoogleEmail) ? $ac->text->gs_yourGoogleEmail : esc_html__('Your Google email', 'flx-forms'),
            /* translators: Optional field marker */
            "gs_recommended" => $state && isset($ac->text->gs_recommended) ? $ac->text->gs_recommended : esc_html__('(recommended)', 'flx-forms'),
            /* translators: Your Google email field hint (HTML allowed) */
            "gs_yourGoogleEmailHint" => $state && isset($ac->text->gs_yourGoogleEmailHint) ? $ac->text->gs_yourGoogleEmailHint : __('Any sheet the plugin <strong>creates</strong> is automatically shared to this address, so it appears in your own Google Drive under “Shared with me.” Without it, new sheets stay hidden inside the service account.', 'flx-forms'),
            /* translators: Enable integration toggle title */
            "gs_enableIntegration" => $state && isset($ac->text->gs_enableIntegration) ? $ac->text->gs_enableIntegration : esc_html__('Enable Google Sheet Integration', 'flx-forms'),
            /* translators: Enable integration toggle subtitle */
            "gs_enableIntegrationSub" => $state && isset($ac->text->gs_enableIntegrationSub) ? $ac->text->gs_enableIntegrationSub : esc_html__('Sync form submissions to your spreadsheets automatically', 'flx-forms'),
            /* translators: Dropzone title */
            "gs_dropzoneTitle" => $state && isset($ac->text->gs_dropzoneTitle) ? $ac->text->gs_dropzoneTitle : esc_html__('Drag & drop service account JSON file(s) here', 'flx-forms'),
            /* translators: Dropzone subtitle */
            "gs_dropzoneSub" => $state && isset($ac->text->gs_dropzoneSub) ? $ac->text->gs_dropzoneSub : esc_html__('or click to browse — you can add multiple service accounts at once', 'flx-forms'),
            /* translators: Connected accounts list title */
            "gs_connectedAccounts" => $state && isset($ac->text->gs_connectedAccounts) ? $ac->text->gs_connectedAccounts : esc_html__('Connected service accounts', 'flx-forms'),
            /* translators: Tips card title */
            "gs_setupTitle" => $state && isset($ac->text->gs_setupTitle) ? $ac->text->gs_setupTitle : esc_html__('One-time setup (≈3 min)', 'flx-forms'),
            /* translators: Setup tip 1 (HTML allowed) */
            "gs_tip1" => $state && isset($ac->text->gs_tip1) ? $ac->text->gs_tip1 : __('Go to<a href="https://console.cloud.google.com/" target="_blank" rel="noopener noreferrer">console.cloud.google.com</a>→ Project dropdown (top-left) → New Project → name it → Create', 'flx-forms'),
            /* translators: Setup tip 2 (HTML allowed) */
            "gs_tip2" => $state && isset($ac->text->gs_tip2) ? $ac->text->gs_tip2 : __('APIs & Services → Library → enable<strong>Google Sheets API</strong><em>and</em><strong>Google Drive API</strong>', 'flx-forms'),
            /* translators: Setup tip 3 (HTML allowed) */
            "gs_tip3" => $state && isset($ac->text->gs_tip3) ? $ac->text->gs_tip3 : __('APIs & Services → Credentials → Create Credentials → Service Account → name it → Done', 'flx-forms'),
            /* translators: Setup tip 4 (HTML allowed) */
            "gs_tip4" => $state && isset($ac->text->gs_tip4) ? $ac->text->gs_tip4 : __('Open the service account → Keys tab → Add Key → Create New Key → JSON (downloads automatically)', 'flx-forms'),
            /* translators: Setup tip 5 */
            "gs_tip5" => $state && isset($ac->text->gs_tip5) ? $ac->text->gs_tip5 : esc_html__('Drag that downloaded file into the box on the left — it is tested automatically', 'flx-forms'),
            /* translators: Setup tip 6 (HTML allowed) */
            "gs_tip6" => $state && isset($ac->text->gs_tip6) ? $ac->text->gs_tip6 : __('Add <strong>Your Google email</strong> above so created sheets land in your Drive', 'flx-forms'),
            /* translators: Invalid JSON file alert */
            "gs_chooseValidJson" => $state && isset($ac->text->gs_chooseValidJson) ? $ac->text->gs_chooseValidJson : esc_html__('Please choose a valid .json key file.', 'flx-forms'),
            /* translators: File read error alert. %s = file name */
            "gs_couldNotReadFile" => $state && isset($ac->text->gs_couldNotReadFile) ? $ac->text->gs_couldNotReadFile : esc_html__('Could not read file: %s', 'flx-forms'),
            /* translators: Connecting service account status */
            "gs_connectingAccount" => $state && isset($ac->text->gs_connectingAccount) ? $ac->text->gs_connectingAccount : esc_html__('Connecting service account...', 'flx-forms'),
            /* translators: Service account added fallback message */
            "gs_accountAdded" => $state && isset($ac->text->gs_accountAdded) ? $ac->text->gs_accountAdded : esc_html__('Service account added.', 'flx-forms'),
            /* translators: My-email saved message. %s = email address */
            "gs_emailSaved" => $state && isset($ac->text->gs_emailSaved) ? $ac->text->gs_emailSaved : esc_html__('Saved. New sheets created by the plugin will be shared to %s automatically.', 'flx-forms'),
            /* translators: My-email cleared message */
            "gs_emailCleared" => $state && isset($ac->text->gs_emailCleared) ? $ac->text->gs_emailCleared : esc_html__('Email cleared. New sheets will stay inside the service account until you share them.', 'flx-forms'),
            /* translators: Confirm remove service account */
            "gs_confirmRemoveAccount" => $state && isset($ac->text->gs_confirmRemoveAccount) ? $ac->text->gs_confirmRemoveAccount : esc_html__('Remove this service account?', 'flx-forms'),
            /* translators: Testing connection status */
            "gs_testingConnection" => $state && isset($ac->text->gs_testingConnection) ? $ac->text->gs_testingConnection : esc_html__('Testing connection...', 'flx-forms'),

            /* ===== Bindings tab ===== */
            /* translators: Section card subtitle — bindings */
            "gs_bindingsSub" => $state && isset($ac->text->gs_bindingsSub) ? $ac->text->gs_bindingsSub : esc_html__('Connect each form to a specific Google Sheet tab', 'flx-forms'),
            /* translators: Section card subtitle — bindings (with steps) */
            "gs_bindingsSubSteps" => $state && isset($ac->text->gs_bindingsSubSteps) ? $ac->text->gs_bindingsSubSteps : esc_html__('Connect each form to a specific Google Sheet tab — step by step', 'flx-forms'),
            /* translators: No service account warning — bold lead */
            "gs_noAccountLead" => $state && isset($ac->text->gs_noAccountLead) ? $ac->text->gs_noAccountLead : esc_html__('No service account connected.', 'flx-forms'),
            /* translators: No service account warning — body */
            "gs_noAccountBody" => $state && isset($ac->text->gs_noAccountBody) ? $ac->text->gs_noAccountBody : esc_html__('Go to the Connections tab and drop a service account JSON key first.', 'flx-forms'),
            /* translators: Binding status — active */
            "gs_active" => $state && isset($ac->text->gs_active) ? $ac->text->gs_active : esc_html__('Active', 'flx-forms'),
            /* translators: Binding status — paused */
            "gs_paused" => $state && isset($ac->text->gs_paused) ? $ac->text->gs_paused : esc_html__('Paused', 'flx-forms'),
            /* translators: Last sync succeeded badge */
            "gs_lastSyncOk" => $state && isset($ac->text->gs_lastSyncOk) ? $ac->text->gs_lastSyncOk : esc_html__('Last sync OK', 'flx-forms'),
            /* translators: Last sync failed badge */
            "gs_lastSyncFailed" => $state && isset($ac->text->gs_lastSyncFailed) ? $ac->text->gs_lastSyncFailed : esc_html__('Last sync failed', 'flx-forms'),
            /* translators: Inline delete confirm text */
            "gs_removeSyncSafe" => $state && isset($ac->text->gs_removeSyncSafe) ? $ac->text->gs_removeSyncSafe : esc_html__('Remove sync? The sheet stays safe.', 'flx-forms'),
            /* translators: Inline delete confirm — yes */
            "gs_yesRemove" => $state && isset($ac->text->gs_yesRemove) ? $ac->text->gs_yesRemove : esc_html__('Yes, remove', 'flx-forms'),
            /* translators: Delete-binding row button tooltip */
            "gs_deleteRowTitle" => $state && isset($ac->text->gs_deleteRowTitle) ? $ac->text->gs_deleteRowTitle : esc_html__('Remove this sync (your Google Sheet is not deleted)', 'flx-forms'),
            /* translators: Existing bindings list title */
            "gs_existingBindings" => $state && isset($ac->text->gs_existingBindings) ? $ac->text->gs_existingBindings : esc_html__('Existing Bindings', 'flx-forms'),
            /* translators: Delete all bindings button */
            "gs_deleteAll" => $state && isset($ac->text->gs_deleteAll) ? $ac->text->gs_deleteAll : esc_html__('Delete All', 'flx-forms'),
            /* translators: New binding button */
            "gs_newBinding" => $state && isset($ac->text->gs_newBinding) ? $ac->text->gs_newBinding : esc_html__('New Binding', 'flx-forms'),
            /* translators: Bindings table column — form */
            "gs_colForm" => $state && isset($ac->text->gs_colForm) ? $ac->text->gs_colForm : esc_html__('Form', 'flx-forms'),
            /* translators: Bindings table column — spreadsheet id */
            "gs_colSpreadsheetId" => $state && isset($ac->text->gs_colSpreadsheetId) ? $ac->text->gs_colSpreadsheetId : esc_html__('Spreadsheet ID', 'flx-forms'),
            /* translators: Bindings table column — tab */
            "gs_colTab" => $state && isset($ac->text->gs_colTab) ? $ac->text->gs_colTab : esc_html__('tab', 'flx-forms'),
            /* translators: Bindings table column — status */
            "gs_colStatus" => $state && isset($ac->text->gs_colStatus) ? $ac->text->gs_colStatus : esc_html__('Status', 'flx-forms'),

            /* ===== Wizard stepper ===== */
            /* translators: Wizard step 1 label */
            "gs_stepSelectForm" => $state && isset($ac->text->gs_stepSelectForm) ? $ac->text->gs_stepSelectForm : esc_html__('Select Form', 'flx-forms'),
            /* translators: Wizard step 2 label */
            "gs_stepChooseSheet" => $state && isset($ac->text->gs_stepChooseSheet) ? $ac->text->gs_stepChooseSheet : esc_html__('Choose Sheet', 'flx-forms'),
            /* translators: Wizard step 3 label */
            "gs_stepColumns" => $state && isset($ac->text->gs_stepColumns) ? $ac->text->gs_stepColumns : esc_html__('Columns & Fields', 'flx-forms'),
            /* translators: Wizard step 4 label */
            "gs_stepStyle" => $state && isset($ac->text->gs_stepStyle) ? $ac->text->gs_stepStyle : esc_html__('Style & Save', 'flx-forms'),

            /* ===== Wizard step 1 (form) ===== */
            /* translators: Form select placeholder */
            "gs_selectAForm" => $state && isset($ac->text->gs_selectAForm) ? $ac->text->gs_selectAForm : esc_html__('— Select a Form —', 'flx-forms'),
            /* translators: Form select question label */
            "gs_whichForm" => $state && isset($ac->text->gs_whichForm) ? $ac->text->gs_whichForm : esc_html__('Which form should send data to Google Sheets?', 'flx-forms'),
            /* translators: Alert — select a form first */
            "gs_selectFormFirst" => $state && isset($ac->text->gs_selectFormFirst) ? $ac->text->gs_selectFormFirst : esc_html__('Please select a form first.', 'flx-forms'),

            /* ===== Wizard step 2 (sheet) ===== */
            /* translators: Service account select label */
            "gs_serviceAccount" => $state && isset($ac->text->gs_serviceAccount) ? $ac->text->gs_serviceAccount : esc_html__('Service Account', 'flx-forms'),
            /* translators: Loading spreadsheets status */
            "gs_loadingSheets" => $state && isset($ac->text->gs_loadingSheets) ? $ac->text->gs_loadingSheets : esc_html__('Loading your spreadsheets...', 'flx-forms'),
            /* translators: Browse/create hint (HTML allowed) */
            "gs_browseOrCreateHint" => $state && isset($ac->text->gs_browseOrCreateHint) ? $ac->text->gs_browseOrCreateHint : __('Click <strong>“Browse My Sheets”</strong> to list spreadsheets this service account can already access, or <strong>“Create New Sheet”</strong> to make a fresh one.', 'flx-forms'),
            /* translators: No accessible sheets message — lead (HTML allowed) */
            "gs_noSheetsLead" => $state && isset($ac->text->gs_noSheetsLead) ? $ac->text->gs_noSheetsLead : __('<strong>No spreadsheets are accessible to this service account yet.</strong> You have two options:', 'flx-forms'),
            /* translators: No accessible sheets — easiest option (HTML allowed) */
            "gs_noSheetsEasiest" => $state && isset($ac->text->gs_noSheetsEasiest) ? $ac->text->gs_noSheetsEasiest : __('<strong>Easiest:</strong> click <em>“Create New Sheet”</em> above — it is made for you and shared to your email automatically.', 'flx-forms'),
            /* translators: No accessible sheets — existing option prefix (HTML allowed) */
            "gs_noSheetsExisting" => $state && isset($ac->text->gs_noSheetsExisting) ? $ac->text->gs_noSheetsExisting : __('<strong>Use an existing sheet:</strong>open it in Google Sheets → Share → paste', 'flx-forms'),
            /* translators: No accessible sheets — existing option suffix (HTML allowed) */
            "gs_noSheetsExistingSuffix" => $state && isset($ac->text->gs_noSheetsExistingSuffix) ? $ac->text->gs_noSheetsExistingSuffix : __('→ Editor, then click<em>Browse My Sheets</em>again.', 'flx-forms'),
            /* translators: Fallback when service account email is unknown */
            "gs_theServiceAccountEmail" => $state && isset($ac->text->gs_theServiceAccountEmail) ? $ac->text->gs_theServiceAccountEmail : esc_html__('the service account email', 'flx-forms'),
            /* translators: Create new spreadsheet field title */
            "gs_createNewSpreadsheet" => $state && isset($ac->text->gs_createNewSpreadsheet) ? $ac->text->gs_createNewSpreadsheet : esc_html__('Create a new spreadsheet', 'flx-forms'),
            /* translators: New sheet title placeholder */
            "gs_newSheetTitlePh" => $state && isset($ac->text->gs_newSheetTitlePh) ? $ac->text->gs_newSheetTitlePh : esc_html__('New spreadsheet title (e.g. Contact form responses)', 'flx-forms'),
            /* translators: New sheet email placeholder */
            "gs_newSheetEmailPh" => $state && isset($ac->text->gs_newSheetEmailPh) ? $ac->text->gs_newSheetEmailPh : esc_html__('Your Google email — the sheet is shared here so you can open it', 'flx-forms'),
            /* translators: New sheet email hint — prefix when prefilled */
            "gs_prefilledEmail" => $state && isset($ac->text->gs_prefilledEmail) ? $ac->text->gs_prefilledEmail : esc_html__('Pre-filled from your saved email.', 'flx-forms'),
            /* translators: New sheet email hint */
            "gs_leaveEmptyHint" => $state && isset($ac->text->gs_leaveEmptyHint) ? $ac->text->gs_leaveEmptyHint : esc_html__('Leave empty only if you don’t need to open the sheet yourself.', 'flx-forms'),
            /* translators: Create sheet button */
            "gs_createSheet" => $state && isset($ac->text->gs_createSheet) ? $ac->text->gs_createSheet : esc_html__('Create Sheet', 'flx-forms'),
            /* translators: Creating sheet status */
            "gs_creating" => $state && isset($ac->text->gs_creating) ? $ac->text->gs_creating : esc_html__('Creating...', 'flx-forms'),
            /* translators: Creating spreadsheet status */
            "gs_creatingSpreadsheet" => $state && isset($ac->text->gs_creatingSpreadsheet) ? $ac->text->gs_creatingSpreadsheet : esc_html__('Creating spreadsheet...', 'flx-forms'),
            /* translators: Sheet created + shared message */
            "gs_sheetCreatedShared" => $state && isset($ac->text->gs_sheetCreatedShared) ? $ac->text->gs_sheetCreatedShared : esc_html__('Sheet created and shared with your email. It’s selected below — click Next to finish.', 'flx-forms'),
            /* translators: Sheet created (not shared) message */
            "gs_sheetCreatedNotShared" => $state && isset($ac->text->gs_sheetCreatedNotShared) ? $ac->text->gs_sheetCreatedNotShared : esc_html__('Sheet created. Tip: add “Your Google email” on the Connections tab so new sheets are shared to you automatically.', 'flx-forms'),
            /* translators: Open the new sheet link label */
            "gs_openNewSheet" => $state && isset($ac->text->gs_openNewSheet) ? $ac->text->gs_openNewSheet : esc_html__('Open the new sheet', 'flx-forms'),
            /* translators: Selected sheet banner prefix */
            "gs_selected" => $state && isset($ac->text->gs_selected) ? $ac->text->gs_selected : esc_html__('Selected:', 'flx-forms'),
            /* translators: Loading worksheet tabs status */
            "gs_loadingTabs" => $state && isset($ac->text->gs_loadingTabs) ? $ac->text->gs_loadingTabs : esc_html__('Loading worksheet tabs...', 'flx-forms'),
            /* translators: Worksheet tab field label */
            "gs_worksheetTab" => $state && isset($ac->text->gs_worksheetTab) ? $ac->text->gs_worksheetTab : esc_html__('Worksheet Tab', 'flx-forms'),
            /* translators: New tab name placeholder */
            "gs_newTabPh" => $state && isset($ac->text->gs_newTabPh) ? $ac->text->gs_newTabPh : esc_html__('Or type a new tab name (created automatically)', 'flx-forms'),
            /* translators: Browse my sheets button */
            "gs_browseMySheets" => $state && isset($ac->text->gs_browseMySheets) ? $ac->text->gs_browseMySheets : esc_html__('Browse My Sheets', 'flx-forms'),
            /* translators: Loading short status */
            "gs_loadingShort" => $state && isset($ac->text->gs_loadingShort) ? $ac->text->gs_loadingShort : esc_html__('Loading…', 'flx-forms'),
            /* translators: Create new sheet button */
            "gs_createNewSheet" => $state && isset($ac->text->gs_createNewSheet) ? $ac->text->gs_createNewSheet : esc_html__('Create New Sheet', 'flx-forms'),
            /* translators: Next button disabled tooltip */
            "gs_chooseSheetFirst" => $state && isset($ac->text->gs_chooseSheetFirst) ? $ac->text->gs_chooseSheetFirst : esc_html__('Choose or create a spreadsheet first', 'flx-forms'),
            /* translators: Alert — select a service account */
            "gs_selectAccount" => $state && isset($ac->text->gs_selectAccount) ? $ac->text->gs_selectAccount : esc_html__('Please select a service account.', 'flx-forms'),
            /* translators: Alert — choose or create a spreadsheet first */
            "gs_chooseCreateFirst" => $state && isset($ac->text->gs_chooseCreateFirst) ? $ac->text->gs_chooseCreateFirst : esc_html__('Please choose or create a spreadsheet first.', 'flx-forms'),

            /* ===== Wizard step 3 (columns) ===== */
            /* translators: Enable sync toggle title (per form) */
            "gs_enableSyncForm" => $state && isset($ac->text->gs_enableSyncForm) ? $ac->text->gs_enableSyncForm : esc_html__('Enable Sync for this Form', 'flx-forms'),
            /* translators: Enable sync toggle subtitle */
            "gs_enableSyncFormSub" => $state && isset($ac->text->gs_enableSyncFormSub) ? $ac->text->gs_enableSyncFormSub : esc_html__('Disable to pause without deleting the binding', 'flx-forms'),
            /* translators: Reading form fields status */
            "gs_readingFields" => $state && isset($ac->text->gs_readingFields) ? $ac->text->gs_readingFields : esc_html__('Reading this form’s fields...', 'flx-forms'),
            /* translators: Fields unreadable message (HTML allowed) */
            "gs_fieldsUnreadable" => $state && isset($ac->text->gs_fieldsUnreadable) ? $ac->text->gs_fieldsUnreadable : __('We couldn’t read this form’s fields automatically, so column mapping isn’t available. Don’t worry — <strong>every submitted field will still be synced automatically</strong>, each in its own column, in submission order. The header row is created and kept up to date for you.', 'flx-forms'),
            /* translators: Try again button */
            "gs_tryAgain" => $state && isset($ac->text->gs_tryAgain) ? $ac->text->gs_tryAgain : esc_html__('Try again', 'flx-forms'),
            /* translators: Column mapping intro (HTML allowed) */
            "gs_mapIntro" => $state && isset($ac->text->gs_mapIntro) ? $ac->text->gs_mapIntro : __('Drag rows (or use ▲▼) to set the<strong>column order</strong>. Rename any<strong>header</strong>. Turn a field off to skip its column. The letter (A, B, C...) shows where each field lands.', 'flx-forms'),
            /* translators: Column map header — column letter */
            "gs_mapColHead" => $state && isset($ac->text->gs_mapColHead) ? $ac->text->gs_mapColHead : esc_html__('Col', 'flx-forms'),
            /* translators: Column map header — form field */
            "gs_mapFieldHead" => $state && isset($ac->text->gs_mapFieldHead) ? $ac->text->gs_mapFieldHead : esc_html__('Form field', 'flx-forms'),
            /* translators: Column map header — spreadsheet header */
            "gs_mapHeaderHead" => $state && isset($ac->text->gs_mapHeaderHead) ? $ac->text->gs_mapHeaderHead : esc_html__('Spreadsheet header', 'flx-forms'),
            /* translators: Column map header — order/on */
            "gs_mapActionsHead" => $state && isset($ac->text->gs_mapActionsHead) ? $ac->text->gs_mapActionsHead : esc_html__('Order / On', 'flx-forms'),
            /* translators: Refresh fields button */
            "gs_refreshFields" => $state && isset($ac->text->gs_refreshFields) ? $ac->text->gs_refreshFields : esc_html__('Refresh fields from form', 'flx-forms'),
            /* translators: Columns & field mapping card title */
            "gs_columnsMapping" => $state && isset($ac->text->gs_columnsMapping) ? $ac->text->gs_columnsMapping : esc_html__('Columns & Field Mapping', 'flx-forms'),
            /* translators: Built-in timestamp field name */
            "gs_submittedTime" => $state && isset($ac->text->gs_submittedTime) ? $ac->text->gs_submittedTime : esc_html__('Submitted time', 'flx-forms'),
            /* translators: Built-in field marker */
            "gs_builtin" => $state && isset($ac->text->gs_builtin) ? $ac->text->gs_builtin : esc_html__('built-in', 'flx-forms'),
            /* translators: Default timestamp header value */
            "gs_submittedAt" => $state && isset($ac->text->gs_submittedAt) ? $ac->text->gs_submittedAt : esc_html__('Submitted At', 'flx-forms'),
            /* translators: Column header input placeholder */
            "gs_columnHeaderPh" => $state && isset($ac->text->gs_columnHeaderPh) ? $ac->text->gs_columnHeaderPh : esc_html__('Column header', 'flx-forms'),
            /* translators: Drag-to-reorder tooltip */
            "gs_dragReorder" => $state && isset($ac->text->gs_dragReorder) ? $ac->text->gs_dragReorder : esc_html__('Drag to reorder', 'flx-forms'),
            /* translators: Move up tooltip */
            "gs_moveUp" => $state && isset($ac->text->gs_moveUp) ? $ac->text->gs_moveUp : esc_html__('Move up', 'flx-forms'),
            /* translators: Move down tooltip */
            "gs_moveDown" => $state && isset($ac->text->gs_moveDown) ? $ac->text->gs_moveDown : esc_html__('Move down', 'flx-forms'),
            /* translators: Include this field tooltip */
            "gs_includeField" => $state && isset($ac->text->gs_includeField) ? $ac->text->gs_includeField : esc_html__('Include this field', 'flx-forms'),
            /* translators: Columns count — singular. %d = count (paired with gs_columnsWrittenPlural via _n()) */
            "gs_columnsWritten" => $state && isset($ac->text->gs_columnsWritten) ? $ac->text->gs_columnsWritten : esc_html(_n('%d column will be written to the sheet.', '%d columns will be written to the sheet.', 1, 'flx-forms')),
            /* translators: Columns count — plural. %d = count (paired with gs_columnsWritten via _n()) */
            "gs_columnsWrittenPlural" => $state && isset($ac->text->gs_columnsWrittenPlural) ? $ac->text->gs_columnsWrittenPlural : esc_html(_n('%d column will be written to the sheet.', '%d columns will be written to the sheet.', 2, 'flx-forms')),

            /* ===== Wizard step 4 (style) ===== */
            /* translators: Sheet style field label */
            "gs_sheetStyle" => $state && isset($ac->text->gs_sheetStyle) ? $ac->text->gs_sheetStyle : esc_html__('Sheet Style', 'flx-forms'),
            /* translators: Sheet style — google theme marker */
            "gs_googleTheme" => $state && isset($ac->text->gs_googleTheme) ? $ac->text->gs_googleTheme : esc_html__('(Google theme)', 'flx-forms'),
            /* translators: Sheet style hint */
            "gs_styleHint" => $state && isset($ac->text->gs_styleHint) ? $ac->text->gs_styleHint : esc_html__('Pick a look for the sheet — colored header, frozen top row and alternating row colors (Google Sheets’ own styling). It’s applied automatically on the first sync, or apply it now.', 'flx-forms'),
            /* translators: Apply style now button */
            "gs_applyStyleNow" => $state && isset($ac->text->gs_applyStyleNow) ? $ac->text->gs_applyStyleNow : esc_html__('Apply style now', 'flx-forms'),
            /* translators: Applying style status */
            "gs_applying" => $state && isset($ac->text->gs_applying) ? $ac->text->gs_applying : esc_html__('Applying...', 'flx-forms'),
            /* translators: Apply style optional note */
            "gs_applyStyleOptional" => $state && isset($ac->text->gs_applyStyleOptional) ? $ac->text->gs_applyStyleOptional : esc_html__('Optional — it also applies on the first submission.', 'flx-forms'),
            /* translators: Summary — spreadsheet */
            "gs_summarySpreadsheet" => $state && isset($ac->text->gs_summarySpreadsheet) ? $ac->text->gs_summarySpreadsheet : esc_html__('Spreadsheet:', 'flx-forms'),
            /* translators: Summary — tab */
            "gs_summaryTab" => $state && isset($ac->text->gs_summaryTab) ? $ac->text->gs_summaryTab : esc_html__('Tab:', 'flx-forms'),
            /* translators: Summary — columns */
            "gs_summaryColumns" => $state && isset($ac->text->gs_summaryColumns) ? $ac->text->gs_summaryColumns : esc_html__('Columns:', 'flx-forms'),
            /* translators: Summary — columns mapped suffix. %d = count */
            "gs_summaryMapped" => $state && isset($ac->text->gs_summaryMapped) ? $ac->text->gs_summaryMapped : esc_html__('%d mapped', 'flx-forms'),
            /* translators: Summary — all submitted fields (auto) */
            "gs_summaryAllFields" => $state && isset($ac->text->gs_summaryAllFields) ? $ac->text->gs_summaryAllFields : esc_html__('all submitted fields (auto)', 'flx-forms'),
            /* translators: Summary — style */
            "gs_summaryStyle" => $state && isset($ac->text->gs_summaryStyle) ? $ac->text->gs_summaryStyle : esc_html__('Style:', 'flx-forms'),
            /* translators: Summary hint */
            "gs_summaryHint" => $state && isset($ac->text->gs_summaryHint) ? $ac->text->gs_summaryHint : esc_html__('After saving, submit the form once to create the header row and confirm the first sync.', 'flx-forms'),
            /* translators: Save binding button */
            "gs_saveBinding" => $state && isset($ac->text->gs_saveBinding) ? $ac->text->gs_saveBinding : esc_html__('Save Binding', 'flx-forms'),
            /* translators: Style name — none */
            "gs_styleNone" => $state && isset($ac->text->gs_styleNone) ? $ac->text->gs_styleNone : esc_html__('None', 'flx-forms'),
            /* translators: Alert — choose a spreadsheet first */
            "gs_chooseSpreadsheetFirst" => $state && isset($ac->text->gs_chooseSpreadsheetFirst) ? $ac->text->gs_chooseSpreadsheetFirst : esc_html__('Please choose a spreadsheet first.', 'flx-forms'),
            /* translators: Alert — pick a style first */
            "gs_pickStyleFirst" => $state && isset($ac->text->gs_pickStyleFirst) ? $ac->text->gs_pickStyleFirst : esc_html__('Pick a style first — “None” leaves the sheet unstyled.', 'flx-forms'),
            /* translators: Style applied success */
            "gs_styleApplied" => $state && isset($ac->text->gs_styleApplied) ? $ac->text->gs_styleApplied : esc_html__('Style applied.', 'flx-forms'),

            /* ===== Save / delete binding ===== */
            /* translators: Alert — select a form */
            "gs_selectForm" => $state && isset($ac->text->gs_selectForm) ? $ac->text->gs_selectForm : esc_html__('Please select a form.', 'flx-forms'),
            /* translators: Alert — choose a spreadsheet */
            "gs_chooseSpreadsheet" => $state && isset($ac->text->gs_chooseSpreadsheet) ? $ac->text->gs_chooseSpreadsheet : esc_html__('Please choose a spreadsheet.', 'flx-forms'),
            /* translators: Binding saved message */
            "gs_bindingSaved" => $state && isset($ac->text->gs_bindingSaved) ? $ac->text->gs_bindingSaved : esc_html__('Binding saved.', 'flx-forms'),
            /* translators: Confirm remove single binding */
            "gs_confirmRemoveBinding" => $state && isset($ac->text->gs_confirmRemoveBinding) ? $ac->text->gs_confirmRemoveBinding : esc_html__('Remove this binding? Syncing stops for this form. Your Google Sheet and its data are not deleted.', 'flx-forms'),
            /* translators: Binding removed message */
            "gs_syncRemoved" => $state && isset($ac->text->gs_syncRemoved) ? $ac->text->gs_syncRemoved : esc_html__('Sync removed — your Google Sheet and its data are untouched.', 'flx-forms'),
            /* translators: Confirm remove all bindings — singular. %d = count (paired with gs_confirmRemoveAllPlural via _n()) */
            "gs_confirmRemoveAll" => $state && isset($ac->text->gs_confirmRemoveAll) ? $ac->text->gs_confirmRemoveAll : esc_html(_n('Remove ALL %d binding? Syncing stops for every form. Your Google Sheets are not deleted.', 'Remove ALL %d bindings? Syncing stops for every form. Your Google Sheets are not deleted.', 1, 'flx-forms')),
            /* translators: Confirm remove all bindings — plural. %d = count (paired with gs_confirmRemoveAll via _n()) */
            "gs_confirmRemoveAllPlural" => $state && isset($ac->text->gs_confirmRemoveAllPlural) ? $ac->text->gs_confirmRemoveAllPlural : esc_html(_n('Remove ALL %d binding? Syncing stops for every form. Your Google Sheets are not deleted.', 'Remove ALL %d bindings? Syncing stops for every form. Your Google Sheets are not deleted.', 2, 'flx-forms')),
            /* translators: All bindings removed message */
            "gs_allBindingsRemoved" => $state && isset($ac->text->gs_allBindingsRemoved) ? $ac->text->gs_allBindingsRemoved : esc_html__('All bindings removed. Your Google Sheets are untouched.', 'flx-forms'),
            /* translators: Connection successful message */
            "gs_connectionSuccessful" => $state && isset($ac->text->gs_connectionSuccessful) ? $ac->text->gs_connectionSuccessful : esc_html__('Connection successful!', 'flx-forms'),

            /* ===== Help tab ===== */
            /* translators: Help section card title */
            "gs_helpTitle" => $state && isset($ac->text->gs_helpTitle) ? $ac->text->gs_helpTitle : esc_html__('Help & Setup Guide', 'flx-forms'),
            /* translators: Help section card subtitle */
            "gs_helpSubtitle" => $state && isset($ac->text->gs_helpSubtitle) ? $ac->text->gs_helpSubtitle : esc_html__('Everything you need to connect forms to Google Sheets', 'flx-forms'),
            /* translators: Help — step-by-step setup title */
            "gs_stepByStep" => $state && isset($ac->text->gs_stepByStep) ? $ac->text->gs_stepByStep : esc_html__('Step-by-Step Setup', 'flx-forms'),
            /* translators: Guide step 1 title */
            "gs_guide1Title" => $state && isset($ac->text->gs_guide1Title) ? $ac->text->gs_guide1Title : esc_html__('Create or Select a Project', 'flx-forms'),
            /* translators: Guide step 1 description (HTML allowed) */
            "gs_guide1Desc" => $state && isset($ac->text->gs_guide1Desc) ? $ac->text->gs_guide1Desc : __('Open console.cloud.google.com and log in → click the Project dropdown (top-left) → New Project (or pick an existing one) → enter a name → Create', 'flx-forms'),
            /* translators: Guide step 2 title */
            "gs_guide2Title" => $state && isset($ac->text->gs_guide2Title) ? $ac->text->gs_guide2Title : esc_html__('Enable both Google APIs', 'flx-forms'),
            /* translators: Guide step 2 description (HTML allowed) */
            "gs_guide2Desc" => $state && isset($ac->text->gs_guide2Desc) ? $ac->text->gs_guide2Desc : __('APIs & Services → Library → enable<strong>Google Sheets API</strong>and<strong>Google Drive API</strong>(both are required — Drive lists and creates the files)', 'flx-forms'),
            /* translators: Guide step 3 title */
            "gs_guide3Title" => $state && isset($ac->text->gs_guide3Title) ? $ac->text->gs_guide3Title : esc_html__('Create a Service Account', 'flx-forms'),
            /* translators: Guide step 3 description (HTML allowed) */
            "gs_guide3Desc" => $state && isset($ac->text->gs_guide3Desc) ? $ac->text->gs_guide3Desc : __('APIs & Services → Credentials → Create Credentials → Service Account → enter a name → Done', 'flx-forms'),
            /* translators: Guide step 4 title */
            "gs_guide4Title" => $state && isset($ac->text->gs_guide4Title) ? $ac->text->gs_guide4Title : esc_html__('Create a JSON Key', 'flx-forms'),
            /* translators: Guide step 4 description (HTML allowed) */
            "gs_guide4Desc" => $state && isset($ac->text->gs_guide4Desc) ? $ac->text->gs_guide4Desc : __('Open the service account → Keys tab → Add Key → Create New Key → choose JSON (file downloads automatically)', 'flx-forms'),
            /* translators: Guide step 5 title */
            "gs_guide5Title" => $state && isset($ac->text->gs_guide5Title) ? $ac->text->gs_guide5Title : esc_html__('Drop the Key File', 'flx-forms'),
            /* translators: Guide step 5 description */
            "gs_guide5Desc" => $state && isset($ac->text->gs_guide5Desc) ? $ac->text->gs_guide5Desc : esc_html__('Drag the downloaded file into the Connections tab — it is verified automatically and you’ll see a green “Connected” badge', 'flx-forms'),
            /* translators: Guide step 6 title */
            "gs_guide6Title" => $state && isset($ac->text->gs_guide6Title) ? $ac->text->gs_guide6Title : esc_html__('Add Your Google Email', 'flx-forms'),
            /* translators: Guide step 6 description */
            "gs_guide6Desc" => $state && isset($ac->text->gs_guide6Desc) ? $ac->text->gs_guide6Desc : esc_html__('On the Connections tab, fill in “Your Google email” and Save — sheets the plugin creates are then shared to you so they appear in your own Drive', 'flx-forms'),
            /* translators: Guide step 7 title */
            "gs_guide7Title" => $state && isset($ac->text->gs_guide7Title) ? $ac->text->gs_guide7Title : esc_html__('Bind a Form (create a sheet)', 'flx-forms'),
            /* translators: Guide step 7 description (HTML allowed) */
            "gs_guide7Desc" => $state && isset($ac->text->gs_guide7Desc) ? $ac->text->gs_guide7Desc : __('Form Bindings → pick a form →<strong>Create New Sheet</strong>→ Save. No manual sharing needed — this is the easiest path', 'flx-forms'),
            /* translators: Guide step 8 title */
            "gs_guide8Title" => $state && isset($ac->text->gs_guide8Title) ? $ac->text->gs_guide8Title : esc_html__('...or use an existing sheet', 'flx-forms'),
            /* translators: Guide step 8 description */
            "gs_guide8Desc" => $state && isset($ac->text->gs_guide8Desc) ? $ac->text->gs_guide8Desc : esc_html__('Prefer your own sheet? Open it → Share → paste the service account email (copy button on its card) → Editor → then Browse My Sheets in the wizard', 'flx-forms'),
            /* translators: FAQ section title */
            "gs_faq" => $state && isset($ac->text->gs_faq) ? $ac->text->gs_faq : esc_html__('FAQ', 'flx-forms'),
            /* translators: FAQ 1 question */
            "gs_faq1Q" => $state && isset($ac->text->gs_faq1Q) ? $ac->text->gs_faq1Q : esc_html__('I created a sheet — where did it go?', 'flx-forms'),
            /* translators: FAQ 1 answer */
            "gs_faq1A" => $state && isset($ac->text->gs_faq1A) ? $ac->text->gs_faq1A : esc_html__('Service accounts create files in their own hidden Drive. Add “Your Google email” on the Connections tab and the plugin shares every new sheet to you automatically — it then appears under “Shared with me” in your Google Drive. Each sheet also has an “Open” link right in this dashboard.', 'flx-forms'),
            /* translators: FAQ 2 question */
            "gs_faq2Q" => $state && isset($ac->text->gs_faq2Q) ? $ac->text->gs_faq2Q : esc_html__('Why don’t I see my existing sheet in the picker?', 'flx-forms'),
            /* translators: FAQ 2 answer */
            "gs_faq2A" => $state && isset($ac->text->gs_faq2A) ? $ac->text->gs_faq2A : esc_html__('It must be shared with the service account’s email first: open the sheet → Share → paste the email (use the copy button on the connection card) → set Editor → Share. Then click “Browse My Sheets” again.', 'flx-forms'),
            /* translators: FAQ 3 question */
            "gs_faq3Q" => $state && isset($ac->text->gs_faq3Q) ? $ac->text->gs_faq3Q : esc_html__('Test result: Google API is not enabled.', 'flx-forms'),
            /* translators: FAQ 3 answer */
            "gs_faq3A" => $state && isset($ac->text->gs_faq3A) ? $ac->text->gs_faq3A : esc_html__('Open Google Cloud Console for the same project → APIs & Services → Library, and enable BOTH “Google Sheets API” and “Google Drive API”. Wait a minute, then retest.', 'flx-forms'),
            /* translators: FAQ 4 question */
            "gs_faq4Q" => $state && isset($ac->text->gs_faq4Q) ? $ac->text->gs_faq4Q : esc_html__('Test result: Access/Permission denied', 'flx-forms'),
            /* translators: FAQ 4 answer */
            "gs_faq4A" => $state && isset($ac->text->gs_faq4A) ? $ac->text->gs_faq4A : esc_html__('The service account can authenticate but can’t reach that specific spreadsheet. Re-share the sheet with the service account email as Editor, or create a new sheet instead.', 'flx-forms'),
            /* translators: FAQ 5 question */
            "gs_faq5Q" => $state && isset($ac->text->gs_faq5Q) ? $ac->text->gs_faq5Q : esc_html__('OpenSSL is required.', 'flx-forms'),
            /* translators: FAQ 5 answer */
            "gs_faq5A" => $state && isset($ac->text->gs_faq5A) ? $ac->text->gs_faq5A : esc_html__('The server is missing the PHP OpenSSL extension used to sign the secure Google request. Ask your host to enable it — no plugin setting can replace it.', 'flx-forms'),
            /* translators: FAQ 6 question */
            "gs_faq6Q" => $state && isset($ac->text->gs_faq6Q) ? $ac->text->gs_faq6Q : esc_html__('Can I connect more than one service account?', 'flx-forms'),
            /* translators: FAQ 6 answer */
            "gs_faq6A" => $state && isset($ac->text->gs_faq6A) ? $ac->text->gs_faq6A : esc_html__('Yes — drop as many JSON keys as you like in the Connections tab and pick which one each form binding should use.', 'flx-forms'),
            /* translators: FAQ 7 question */
            "gs_faq7Q" => $state && isset($ac->text->gs_faq7Q) ? $ac->text->gs_faq7Q : esc_html__('Does the connection expire?', 'flx-forms'),
            /* translators: FAQ 7 answer */
            "gs_faq7A" => $state && isset($ac->text->gs_faq7A) ? $ac->text->gs_faq7A : esc_html__('No. The integration runs entirely on the server using your JSON key — there’s no browser session or login to expire.', 'flx-forms'),
            /* translators: FAQ 8 question */
            "gs_faq8Q" => $state && isset($ac->text->gs_faq8Q) ? $ac->text->gs_faq8Q : esc_html__('Can I choose which field goes to which column?', 'flx-forms'),
            /* translators: FAQ 8 answer (HTML allowed) */
            "gs_faq8A" => $state && isset($ac->text->gs_faq8A) ? $ac->text->gs_faq8A : __('Yes. In the binding wizard’s<strong>Columns & Fields</strong>step, drag rows (or use ▲▼) to set the order, rename each column header, and toggle any field off to skip it. The A/B/C badge shows exactly where each field lands.', 'flx-forms'),
            /* translators: FAQ 9 question */
            "gs_faq9Q" => $state && isset($ac->text->gs_faq9Q) ? $ac->text->gs_faq9Q : esc_html__('Can I style the sheet automatically?', 'flx-forms'),
            /* translators: FAQ 9 answer (HTML allowed) */
            "gs_faq9A" => $state && isset($ac->text->gs_faq9A) ? $ac->text->gs_faq9A : __('Yes. The<strong>Style & Save</strong>step offers Google-Sheets themes (colored header, frozen top row, alternating row colors). Pick one and it’s applied on the first sync, or click “Apply style now”.', 'flx-forms'),
            /* translators: FAQ 10 question */
            "gs_faq10Q" => $state && isset($ac->text->gs_faq10Q) ? $ac->text->gs_faq10Q : esc_html__('What if my form fields change later?', 'flx-forms'),
            /* translators: FAQ 10 answer */
            "gs_faq10A" => $state && isset($ac->text->gs_faq10A) ? $ac->text->gs_faq10A : esc_html__('New fields are added automatically. In mapped mode, open the Columns step and click “Refresh fields from form” to pull them into the mapping; in “All Form Fields” mode they appear as new columns on the next submission.', 'flx-forms'),
            /* translators: FAQ 11 question */
            "gs_faq11Q" => $state && isset($ac->text->gs_faq11Q) ? $ac->text->gs_faq11Q : esc_html__('What if the tab doesn’t exist yet?', 'flx-forms'),
            /* translators: FAQ 11 answer */
            "gs_faq11A" => $state && isset($ac->text->gs_faq11A) ? $ac->text->gs_faq11A : esc_html__('It is created automatically the first time a submission is synced.', 'flx-forms'),

            /* ===== Logs page ===== */
            /* translators: Logs page title */
            "gs_logsTitle" => $state && isset($ac->text->gs_logsTitle) ? $ac->text->gs_logsTitle : esc_html__('Google Sheet Sync Logs', 'flx-forms'),
            /* translators: Logs page subtitle */
            "gs_logsSubtitle" => $state && isset($ac->text->gs_logsSubtitle) ? $ac->text->gs_logsSubtitle : esc_html__('Latest submissions sent to Google Sheets, success or failure', 'flx-forms'),
            /* translators: Logs — cleared message */
            "gs_logCleared" => $state && isset($ac->text->gs_logCleared) ? $ac->text->gs_logCleared : esc_html__('Log cleared.', 'flx-forms'),
            /* translators: Logs — clear button */
            "gs_clearLog" => $state && isset($ac->text->gs_clearLog) ? $ac->text->gs_clearLog : esc_html__('Clear Log', 'flx-forms'),
            /* translators: Logs — clear confirm */
            "gs_clearAllLogs" => $state && isset($ac->text->gs_clearAllLogs) ? $ac->text->gs_clearAllLogs : esc_html__('Clear all logs?', 'flx-forms'),
            /* translators: Logs — empty state */
            "gs_noSyncYet" => $state && isset($ac->text->gs_noSyncYet) ? $ac->text->gs_noSyncYet : esc_html__('No sync activity yet.', 'flx-forms'),
            /* translators: Logs table — time column */
            "gs_logTime" => $state && isset($ac->text->gs_logTime) ? $ac->text->gs_logTime : esc_html__('Time', 'flx-forms'),
            /* translators: Logs table — event column */
            "gs_logEvent" => $state && isset($ac->text->gs_logEvent) ? $ac->text->gs_logEvent : esc_html__('Event', 'flx-forms'),
            /* translators: Logs table — spreadsheet column */
            "gs_logSpreadsheet" => $state && isset($ac->text->gs_logSpreadsheet) ? $ac->text->gs_logSpreadsheet : esc_html__('Spreadsheet', 'flx-forms'),
            /* translators: Logs table — detail column */
            "gs_logDetail" => $state && isset($ac->text->gs_logDetail) ? $ac->text->gs_logDetail : esc_html__('Detail', 'flx-forms'),
            /* translators: Logs status — synced */
            "gs_synced" => $state && isset($ac->text->gs_synced) ? $ac->text->gs_synced : esc_html__('synchronized', 'flx-forms'),

            /* ===== Sheet style preset gallery (Style & Save step / templates list) ===== */
            /* translators: Style preset name - no colors or formatting applied */
            "gs_templateNone" => $state && isset($ac->text->gs_templateNone) ? $ac->text->gs_templateNone : esc_html__('None (plain)', 'flx-forms'),
            /* translators: Style preset name - light gray header, minimal styling */
            "gs_templateMinimal" => $state && isset($ac->text->gs_templateMinimal) ? $ac->text->gs_templateMinimal : esc_html__('Minimal', 'flx-forms'),
            /* translators: Style preset name - green header theme */
            "gs_templateGreen" => $state && isset($ac->text->gs_templateGreen) ? $ac->text->gs_templateGreen : esc_html__('Green', 'flx-forms'),
            /* translators: Style preset name - blue header theme */
            "gs_templateBlue" => $state && isset($ac->text->gs_templateBlue) ? $ac->text->gs_templateBlue : esc_html__('Blue', 'flx-forms'),
            /* translators: Style preset name - dark slate-gray header theme */
            "gs_templateSlate" => $state && isset($ac->text->gs_templateSlate) ? $ac->text->gs_templateSlate : esc_html__('Slate', 'flx-forms'),
            /* translators: Style preset name - orange header theme */
            "gs_templateSunset" => $state && isset($ac->text->gs_templateSunset) ? $ac->text->gs_templateSunset : esc_html__('Sunset', 'flx-forms'),
            /* translators: Style preset name - purple header theme */
            "gs_templateGrape" => $state && isset($ac->text->gs_templateGrape) ? $ac->text->gs_templateGrape : esc_html__('Grape', 'flx-forms'),
        ];
    }
}

function efb_get_addon_phrases($addon_key, $ac = null, $state = false) {
    EfbAddonPhrases::get_instance();
    return EfbAddonPhrases::get_addon_phrases($addon_key, $ac, $state);
}

function efb_register_addon_phrases($addon_key, $callback) {
    EfbAddonPhrases::get_instance();
    EfbAddonPhrases::register_addon($addon_key, $callback);
}
