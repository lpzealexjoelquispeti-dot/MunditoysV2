export const mundiToysGlbPreview = () => ({
    previewUrl: null,
    fileName: '',
    previewError: '',
    uploading: false,

    selectFile(file) {
        this.releaseUrl();
        this.previewError = '';
        this.fileName = file?.name ?? '';

        if (!file) return;
        if (!/\.glb$/i.test(file.name)) {
            this.previewError = 'Selecciona un archivo con extensión .glb.';
            return;
        }
        if (file.size > 50 * 1024 * 1024) {
            this.previewError = 'El modelo 3D debe pesar como máximo 50 MB.';
            return;
        }

        this.previewUrl = URL.createObjectURL(file);
    },

    uploadFailed() {
        this.uploading = false;
        this.releaseUrl();
        this.previewError = 'No se pudo subir el modelo 3D. Comprueba la conexión y el límite de 50 MB del servidor e inténtalo nuevamente.';
    },

    releaseUrl() {
        if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
        this.previewUrl = null;
    },

    resetPreview() {
        this.releaseUrl();
        this.fileName = '';
        this.previewError = '';
        if (this.$refs?.glbInput) this.$refs.glbInput.value = '';
    },

    destroy() {
        this.releaseUrl();
    },
});
