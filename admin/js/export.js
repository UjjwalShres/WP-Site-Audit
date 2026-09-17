document.addEventListener('DOMContentLoaded', function () {

    const radios = document.querySelectorAll('input[name="wpsa_export_type"]');
    const downloadBtn = document.getElementById('wpsa-download');
    const previewBtn = document.getElementById('wpsa-preview');

    function getSelectedType() {
        let value = 'html';
        radios.forEach(r => {
            if (r.checked) value = r.value;
        });
        return value;
    }

    function updatePreviewVisibility() {
        const type = getSelectedType();

        if (type === 'html') {
            previewBtn.style.display = 'inline-block';
        } else {
            previewBtn.style.display = 'none';
        }
    }

    // Initial
    updatePreviewVisibility();

    // Change event
    radios.forEach(radio => {
        radio.addEventListener('change', updatePreviewVisibility);
    });

    // Download
    downloadBtn.addEventListener('click', function () {

        const type = getSelectedType();

        window.location.href =
            ajaxurl.replace('admin-ajax.php', 'admin-post.php') +
            '?action=wpsa_export&type=' + type;
    });

    // Preview
    previewBtn.addEventListener('click', function () {

        previewBtn.href =
            ajaxurl.replace('admin-ajax.php', 'admin-post.php') +
            '?action=wpsa_export&type=preview';
    });

});
