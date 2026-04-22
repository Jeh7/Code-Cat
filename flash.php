<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function flash_add(string $type, string $message): void
{
    if ($message === '') {
        return;
    }

    if (!isset($_SESSION['_flash_messages']) || !is_array($_SESSION['_flash_messages'])) {
        $_SESSION['_flash_messages'] = [];
    }

    $_SESSION['_flash_messages'][] = [
        'type' => $type,
        'message' => $message,
    ];
}

function flash_consume(): array
{
    $messages = $_SESSION['_flash_messages'] ?? [];
    unset($_SESSION['_flash_messages']);

    return is_array($messages) ? $messages : [];
}

function render_flash_messages(): string
{
    $messages = flash_consume();
    if (!$messages) {
        return '';
    }

    $html = '<div class="flash_stack flash_toast_stack" aria-live="polite" aria-atomic="true">';
    foreach ($messages as $index => $message) {
        $type = htmlspecialchars((string)($message['type'] ?? 'info'));
        $text = htmlspecialchars((string)($message['message'] ?? ''));
        $html .= '<div class="flash_message flash_toast is-' . $type . '" data-toast data-toast-index="' . $index . '">';
        $html .= '<span class="flash_toast_text">' . $text . '</span>';
        $html .= '<button type="button" class="flash_toast_close" data-toast-close aria-label="Dismiss notification">x</button>';
        $html .= '</div>';
    }
    $html .= '</div>';
    $html .= "<script>
    (function () {
        var toasts = document.querySelectorAll('[data-toast]');
        toasts.forEach(function (toast) {
            var closeButton = toast.querySelector('[data-toast-close]');
            var dismiss = function () {
                toast.classList.add('is-hiding');
                window.setTimeout(function () {
                    if (toast && toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 220);
            };

            if (closeButton) {
                closeButton.addEventListener('click', dismiss);
            }

            window.setTimeout(dismiss, 4200);
        });
    }());
    </script>";

    return $html;
}
