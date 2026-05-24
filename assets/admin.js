/* global tinifyAi, jQuery */
(function ($) {
    'use strict';

    // Single-image optimize button
    $(document).on('click', '.tinify-optimize-single', function (e) {
        e.preventDefault();
        var btn = $(this);
        var id  = btn.data('id');
        btn.prop('disabled', true).text('Queuing…');

        $.post(tinifyAi.ajaxUrl, {
            action:        'tinify_optimize_single',
            nonce:         tinifyAi.nonce,
            attachment_id: id,
        }).done(function () {
            btn.text('Queued ✓');
        }).fail(function () {
            btn.prop('disabled', false).text('Retry');
        });
    });

    // Bulk optimize page
    var bulkRunning = false;
    var pollInterval;

    function updateProgress(data) {
        var done  = data.completed;
        var total = data.total;
        var pct   = total > 0 ? Math.round((done / total) * 100) : 0;
        $('#tinify-progress').attr({ value: done, max: total });
        $('#tinify-progress-text').text(done + ' / ' + total + ' optimized (' + pct + '%)');
        if (data.pending === 0 && data.processing === 0 && bulkRunning) {
            clearInterval(pollInterval);
            bulkRunning = false;
            $('#tinify-bulk-start').prop('disabled', false).text('All done ✓');
        }
    }

    function startPolling() {
        clearInterval(pollInterval);
        pollInterval = setInterval(function () {
            $.post(tinifyAi.ajaxUrl, { action: 'tinify_bulk_status', nonce: tinifyAi.nonce })
             .done(function (res) { if (res.success) updateProgress(res.data); });
        }, 3000);
    }

    $('#tinify-bulk-start').on('click', function () {
        var btn = $(this);
        btn.prop('disabled', true).text('Starting…');
        $('#tinify-progress-bar').show();
        bulkRunning = true;

        $.post(tinifyAi.ajaxUrl, { action: 'tinify_bulk_queue', nonce: tinifyAi.nonce, retry_failed: 0 })
         .done(function (res) {
             if (res.success) { startPolling(); btn.text('Optimizing…'); }
         });
    });

    $('#tinify-bulk-retry').on('click', function () {
        $.post(tinifyAi.ajaxUrl, { action: 'tinify_bulk_queue', nonce: tinifyAi.nonce, retry_failed: 1 })
         .done(function (res) { if (res.success) { bulkRunning = true; startPolling(); } });
    });

}(jQuery));
