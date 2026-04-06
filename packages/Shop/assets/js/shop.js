/**
 * Shop Package - 공용 JS
 */
const Shop = {
    formatPrice(price) {
        return parseInt(price).toLocaleString() + '원';
    },

    confirm(message, onConfirm) {
        if (window.confirm(message)) {
            onConfirm();
        }
    }
};
