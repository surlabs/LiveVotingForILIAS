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

    function isSameDomain(url1, url2) {
        const a = document.createElement('a');
        a.href = url1;
        const domain1 = a.hostname;
        a.href = url2;
        const domain2 = a.hostname;
        return domain1 === domain2;
    }
});