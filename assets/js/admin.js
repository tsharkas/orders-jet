/**
 * Orders Jet - Admin JavaScript
 * Handles admin interface interactions
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Regenerate QR Code
    $(document).on('click', '.oj-regenerate-qr', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var tableId = $button.data('table-id');
        var $container = $button.closest('.oj-qr-code-container');
        var $img = $container.find('img');
        var $downloadLink = $container.find('a[download]');
        
        // Show loading state
        $button.prop('disabled', true).text('Regenerating...');
        
        $.ajax({
            url: oj_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'oj_regenerate_qr_code',
                table_id: tableId,
                nonce: oj_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Update QR code image
                    $img.attr('src', response.data.qr_code_url + '&t=' + new Date().getTime());
                    
                    // Update download link
                    $downloadLink.attr('href', response.data.qr_code_url);
                    
                    showNotification('QR code regenerated successfully!', 'success');
                } else {
                    showNotification('Failed to regenerate QR code: ' + response.data.message, 'error');
                }
            },
            error: function() {
                showNotification('Error regenerating QR code. Please try again.', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text('Regenerate QR Code');
            }
        });
    });
    
    // Show notification
    function showNotification(message, type) {
        var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        $('.wrap h1').after($notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut();
        }, 5000);
    }
    
    // Table status updates
    $(document).on('change', 'select[name="oj_table_status"]', function() {
        var $select = $(this);
        var newStatus = $select.val();
        var $row = $select.closest('tr');
        var $badge = $row.find('.oj-status-badge');
        
        // Update badge class
        $badge.removeClass('oj-status-available oj-status-occupied oj-status-reserved oj-status-maintenance');
        $badge.addClass('oj-status-' + newStatus);
        
        // Update badge text
        var statusLabels = {
            'available': 'Available',
            'occupied': 'Occupied',
            'reserved': 'Reserved',
            'maintenance': 'Maintenance'
        };
        $badge.text(statusLabels[newStatus] || newStatus);
    });
    
    // Auto-save table number changes
    $(document).on('blur', 'input[name="oj_table_number"]', function() {
        var $input = $(this);
        var tableNumber = $input.val();
        var postId = $('#post_ID').val();
        
        if (tableNumber && postId) {
            // Update QR code URL
            var $qrUrl = $('.oj-qr-code-url input');
            if ($qrUrl.length) {
                $qrUrl.val(window.location.origin + '/table-menu/?table=' + encodeURIComponent(tableNumber));
            }
        }
    });
    
    // Copy QR URL to clipboard
    $(document).on('click', '.oj-qr-code-url input', function() {
        $(this).select();
        document.execCommand('copy');
        showNotification('QR URL copied to clipboard!', 'success');
    });
    
    // Flush rewrite rules
    $(document).on('click', '.oj-flush-rewrite-rules', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        $button.prop('disabled', true).text('Flushing...');
        
        $.ajax({
            url: oj_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'oj_flush_rewrite_rules',
                nonce: oj_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification('Rewrite rules flushed successfully!', 'success');
                } else {
                    showNotification('Failed to flush rewrite rules: ' + response.data.message, 'error');
                }
            },
            error: function() {
                showNotification('Error flushing rewrite rules. Please try again.', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text('Flush Rewrite Rules');
            }
        });
    });
});
