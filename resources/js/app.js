import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;

window.MerchantUi = {
    rememberDisclosure(key, open) {
        try {
            localStorage.setItem(key, open ? '1' : '0');
        } catch (e) {
            // Ignore private-mode storage failures.
        }
    },
    recallDisclosure(key, fallback = false) {
        try {
            const value = localStorage.getItem(key);
            if (value === '1') {
                return true;
            }
            if (value === '0') {
                return false;
            }
        } catch (e) {
            // Ignore.
        }

        return fallback;
    },
};

Alpine.start();
