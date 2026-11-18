$(document).ready(function() {
    $(document).on('click', '#pinBackButton', function(e) {
        e.preventDefault();

        const referrer = document.referrer;
        const currentUrl = window.location.href;

        if (referrer && referrer !== currentUrl && isSameDomain(referrer, currentUrl)) {
            window.history.back();
        } else {
            window.location.href = '/';
        }
    });

    // The back button comes first in the form, so the browser would use it when pressing Enter.
    $(document).on('keydown', 'input[name="pin_input"]', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            $(this).closest('form').find('[name="cmd[checkPin]"]').trigger('click');
        }
    });

    function isSameDomain(url1, url2) {
        const a = document.createElement('a');
        a.href = url1;
        const domain1 = a.hostname;
        a.href = url2;
        const domain2 = a.hostname;
        return domain1 === domain2;
    }
});