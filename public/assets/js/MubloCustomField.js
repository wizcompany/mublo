/**
 * MubloCustomField — 커스텀 필드 파일 업로드 모듈
 *
 * CustomFieldRenderer::renderFileScript()에서 로드.
 * .custom-field-file 입력에 자동 바인딩.
 */
var MubloCustomField = (function () {
    var uploadUrl = '';

    function setUploadUrl(url) {
        uploadUrl = url;
        initFileUploads();
    }

    function initFileUploads() {
        document.querySelectorAll('.custom-field-file').forEach(function (input) {
            if (input.dataset.cfInit) return;
            input.dataset.cfInit = '1';

            input.addEventListener('change', function () {
                var fieldId = this.dataset.fieldId;
                var prefix = this.dataset.idPrefix || 'field_';
                var maxSizeMb = parseInt(this.dataset.maxSize || '5', 10);
                var file = this.files[0];

                if (!file) return;

                if (file.size > maxSizeMb * 1024 * 1024) {
                    alert('파일 크기가 ' + maxSizeMb + 'MB를 초과했습니다.');
                    this.value = '';
                    return;
                }

                var formData = new FormData();
                formData.append('file', file);
                formData.append('field_id', fieldId);

                MubloRequest.sendRequest({
                    method: 'POST',
                    url: uploadUrl,
                    payloadType: 'form',
                    data: formData,
                }).then(function (res) {
                    var metaInput = document.getElementById(prefix + fieldId + '_meta');
                    if (metaInput) metaInput.value = JSON.stringify(res.data);

                    var resultDiv = document.getElementById(prefix + fieldId + '_result');
                    if (resultDiv) {
                        resultDiv.querySelector('.file-name').textContent = res.data.filename;
                        resultDiv.style.display = 'flex';
                    }

                    var currentDiv = document.getElementById(prefix + fieldId + '_current');
                    if (currentDiv) currentDiv.style.display = 'none';
                });
            });
        });
    }

    function removeFile(prefix, fieldId) {
        var metaInput = document.getElementById(prefix + fieldId + '_meta');
        if (metaInput) metaInput.value = '';

        var fileInput = document.getElementById(prefix + fieldId);
        if (fileInput) fileInput.value = '';

        var resultDiv = document.getElementById(prefix + fieldId + '_result');
        if (resultDiv) resultDiv.style.display = 'none';

        var currentDiv = document.getElementById(prefix + fieldId + '_current');
        if (currentDiv) currentDiv.style.display = 'flex';
    }

    function deleteExisting(prefix, fieldId) {
        if (!confirm('파일을 삭제하시겠습니까?')) return;

        var metaInput = document.getElementById(prefix + fieldId + '_meta');
        if (metaInput) metaInput.value = '__delete__';

        var currentDiv = document.getElementById(prefix + fieldId + '_current');
        if (currentDiv) currentDiv.style.display = 'none';
    }

    // DOM 준비 시 자동 초기화
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFileUploads);
    } else {
        initFileUploads();
    }

    return {
        setUploadUrl: setUploadUrl,
        initFileUploads: initFileUploads,
        removeFile: removeFile,
        deleteExisting: deleteExisting,
    };
})();
