/**
 * Avatar Upload and Preview Handler
 */
(function ($) {
    const AvatarUpload = {
        init: function () {
            this.cacheDOM();
            this.bindEvents();
        },

        cacheDOM: function () {
            this.fileInput = $('#signup_avatar');
            this.previewImg = $('#avatar-preview-img');
            this.previewContainer = $('#avatar-preview');
            this.errorContainer = $('#avatar-error');
        },

        bindEvents: function () {
            this.fileInput.on('change', this.handleFileSelect.bind(this));
        },

        handleFileSelect: function (event) {
            const file = event.target.files[0];

            if (file) {
                this.validateFile(file, (isValid, errorMessage) => {
                    if (isValid) {
                        this.showPreview(file);
                        this.errorContainer.text('').hide();
                    } else {
                        this.showError(errorMessage);
                        this.resetUpload();
                    }
                });
            } else {
                this.resetUpload();
            }
        },

        validateFile: function (file, callback) {
            const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
            const maxSize = avatarUploadStrings.maxFileSize;

            if (!validTypes.includes(file.type)) {
                callback(false, avatarUploadStrings.invalidType);
                return;
            }

            if (file.size > maxSize) {
                callback(false, avatarUploadStrings.fileTooLarge);
                return;
            }

            const img = new Image();
            img.onload = () => {
                URL.revokeObjectURL(img.src);
                if (img.width > avatarUploadStrings.maxDimensions || img.height > avatarUploadStrings.maxDimensions) {
                    callback(false, avatarUploadStrings.dimensionsTooLarge);
                } else {
                    callback(true);
                }
            };
            img.onerror = () => {
                callback(false, avatarUploadStrings.loadFailed);
            };
            img.src = URL.createObjectURL(file);
        },

        showPreview: function (file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                const img = new Image();
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');

                    const size = Math.min(img.width, img.height);
                    const startX = (img.width - size) / 2;
                    const startY = (img.height - size) / 2;

                    canvas.width = canvas.height = 150;
                    ctx.drawImage(img, startX, startY, size, size, 0, 0, 150, 150);

                    const quality = avatarUploadStrings.compression / 100;
                    canvas.toBlob((blob) => {
                        const url = URL.createObjectURL(blob);
                        this.previewImg.attr('src', url);
                        this.previewContainer.show();
                    }, 'image/jpeg', quality);
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        },

        showError: function (message) {
            this.errorContainer.text(message).show();
        },

        resetUpload: function () {
            this.fileInput.val('');
            this.previewContainer.hide();
        }
    };

    $(document).ready(function () {
        AvatarUpload.init();
        if (avatarUploadStrings.required) {
            $('#signup_avatar').prop('required', true);
        }
    });
})(jQuery);