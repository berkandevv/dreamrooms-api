import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.data('confirmableForm', (config = {}) => ({
    confirm: {
        open: false,
        title: '',
        message: '',
        onConfirm: () => {},
    },
    askSave() {
        this.confirm = {
            open: true,
            title: config.saveTitle ?? 'Save changes?',
            message: config.saveMessage ?? 'This will save your changes.',
            onConfirm: () => this.$refs.form.submit(),
        };
    },
    askCancel() {
        this.confirm = {
            open: true,
            title: config.cancelTitle ?? 'Discard changes?',
            message: config.cancelMessage ?? 'Unsaved changes will be lost.',
            onConfirm: () => window.location.href = config.cancelUrl,
        };
    },
}));

Alpine.start();
