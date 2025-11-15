jQuery(function($) {
    var total = parseInt(CASS_Slug_Sync.total, 10) || 0;
    var current = 0;
    var running = false;

    function logLine(msg) {
        $('#cass-log').prepend('<li>' + msg + '</li>');
    }

    function updateProgress() {
        if (!total) return;
        var percent = Math.round((current / total) * 100);
        $('#cass-progress-bar').css('width', percent + '%');
        $('#cass-progress-text').text('Processed ' + current + ' of ' + total + ' items (' + percent + '%).');
    }

    function runStep() {
        if (!running) return;
        if (current >= total) {
            updateProgress();
            logLine('All done.');
            $('#cass-progress-text').text('Completed all slug updates.');
            running = false;
            return;
        }

        $.post(
            CASS_Slug_Sync.ajax_url,
            {
                action: 'cass_slug_sync_step',
                nonce: CASS_Slug_Sync.nonce,
                index: current
            }
        ).done(function(resp) {
            if (!resp || !resp.success) {
                logLine('Error at index ' + current + ': ' + (resp && resp.data && resp.data.message ? resp.data.message : 'Unknown error'));
                current++;
                updateProgress();
                setTimeout(runStep, 500);
                return;
            }

            var d = resp.data || {};
            var msg = '#' + (current + 1) + ': ' + (d.status || 'Done');
            if (d.slug_from && d.slug_to) {
                msg += ' (slug ' + d.slug_from + ' → ' + d.slug_to + ')';
            }
            if (d.post_id) {
                msg += ' [Post ID ' + d.post_id + ']';
            }
            if (d.changes) {
                msg += ' [content: ' + d.changes.content_replacements +
                       ', elementor: ' + d.changes.meta_replacements +
                       ', menu: ' + d.changes.menu_replacements + ']';
            }
            logLine(msg);

            current++;
            updateProgress();
            setTimeout(runStep, 400);
        }).fail(function() {
            logLine('AJAX error at index ' + current + '. Skipping.');
            current++;
            updateProgress();
            setTimeout(runStep, 600);
        });
    }

    $('#cass-start').on('click', function(e) {
        e.preventDefault();
        if (!total) {
            alert('No mappings configured.');
            return;
        }
        if (running) {
            return;
        }
        if (!confirm('Have you taken a recent database backup? This will change slugs and update links.')) {
            return;
        }
        running = true;
        current = 0;
        $('#cass-progress-wrapper').show();
        $('#cass-log').empty();
        $('#cass-progress-bar').css('width', '0%');
        $('#cass-progress-text').text('Starting…');
        runStep();
    });
});
