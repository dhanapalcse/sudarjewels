import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import focus from '@alpinejs/focus';

/**
 * Analytics helper. Sends only catalogue data (SKU, name, price, category) —
 * never names, phone numbers, addresses, OTPs or warranty codes.
 */
window.sjTrack = (event, params = {}) => {
    if (typeof window.gtag === 'function') {
        window.gtag('event', event, params);
    }
};

/** Product gallery: native scroll-snap swipe, thumbnails, keyboard, and a zoom dialog. */
Alpine.data('gallery', (count) => ({
    index: 0,
    count,
    zoomOpen: false,
    zoomed: false,
    origin: '50% 50%',
    go(i) {
        this.index = (i + this.count) % this.count;
        const track = this.$refs.track;
        track.scrollTo({ left: track.clientWidth * this.index, behavior: 'smooth' });
    },
    onScroll() {
        const track = this.$refs.track;
        this.index = Math.round(track.scrollLeft / Math.max(1, track.clientWidth));
    },
    openZoom() {
        this.zoomOpen = true;
        this.zoomed = false;
        this.$nextTick(() => this.$refs.zoomClose?.focus());
    },
    closeZoom() {
        this.zoomOpen = false;
        this.$nextTick(() => this.$refs.zoomTrigger?.focus());
    },
    toggleZoom(e) {
        const r = e.currentTarget.getBoundingClientRect();
        this.origin = `${((e.clientX - r.left) / r.width) * 100}% ${((e.clientY - r.top) / r.height) * 100}%`;
        this.zoomed = !this.zoomed;
    },
}));

/** Variant + quantity selection with validated WhatsApp enquiry. */
Alpine.data('enquiry', (config) => ({
    variants: config.variants,
    variantId: config.variants.length === 1 ? config.variants[0].id : null,
    quantity: 1,
    error: '',
    busy: false,
    get selected() {
        return this.variants.find((v) => v.id === this.variantId) || null;
    },
    get price() {
        return this.selected ? this.selected.price : config.basePrice;
    },
    get maxQty() {
        return this.selected ? Math.max(1, Math.min(10, this.selected.available)) : 10;
    },
    inc() {
        if (this.quantity < this.maxQty) this.quantity++;
    },
    dec() {
        if (this.quantity > 1) this.quantity--;
    },
    validate() {
        if (!this.selected) return 'Please choose an option first.';
        if (this.selected.available < 1) return 'This option is out of stock. Choose another option.';
        if (this.quantity < 1 || this.quantity > this.selected.available) return `Only ${this.selected.available} available.`;
        return '';
    },
    async submit(event) {
        this.error = this.validate();
        if (this.error) {
            event.preventDefault();
            this.$nextTick(() => this.$refs.error?.focus());
            return;
        }
        event.preventDefault();
        this.busy = true;
        // Open the tab synchronously to avoid popup blockers, then point it at WhatsApp.
        const tab = window.open('', '_blank');
        try {
            const res = await fetch(config.endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': config.csrf },
                body: JSON.stringify({ variant_id: this.variantId, quantity: this.quantity }),
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'Something went wrong.');
            window.sjTrack('whatsapp_enquiry', { item_id: this.selected.sku, item_name: config.name, value: (this.price * this.quantity) / 100, currency: 'INR' });
            if (tab) tab.location = data.url;
            else window.location = data.url;
        } catch (e) {
            if (tab) tab.close();
            this.error = e.message;
            this.$nextTick(() => this.$refs.error?.focus());
        } finally {
            this.busy = false;
        }
    },
}));

Alpine.plugin(collapse);
Alpine.plugin(focus);
window.Alpine = Alpine;
Alpine.start();
