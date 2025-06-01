<?php

/**
 * Path to the authentication email templates
 *
 * @var string
 */
if (!defined('AUTH_EMAIL_PATH')) {
    if (file_exists(APPPATH . 'Views/emails/') && file_exists(APPPATH . 'Views/emails/')) {
        define('AUTH_EMAIL_PATH', APPPATH . 'Views/emails');
    } else {
        define('AUTH_EMAIL_PATH', __DIR__ . '/../Views/emails');
    }
}
