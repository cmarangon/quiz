/**
 * Alpine component backing the ordering question's drag-and-drop list.
 *
 * config: { items: string[] }  // labels in the (already shuffled) display order
 *
 * Reordering works via native HTML5 drag events and via up/down buttons so the
 * question stays usable on touch devices that handle drag poorly.
 */
export function orderingList(config) {
    return {
        items: [],
        colors: {},
        dragIndex: null,
        submitted: false,

        init() {
            this.items = Array.from(config.items || []);
            const palette = ['a', 'b', 'c', 'd'];
            this.colors = {};
            this.items.forEach((item, index) => {
                this.colors[item] = palette[index % palette.length];
            });
        },

        colorFor(item) {
            return this.colors[item] ?? 'a';
        },

        onDragStart(index) {
            this.dragIndex = index;
        },

        onDrop(index) {
            const from = this.dragIndex;
            this.dragIndex = null;

            if (from === null || from === index) {
                return;
            }

            const moved = this.items.splice(from, 1)[0];
            this.items.splice(index, 0, moved);
        },

        moveUp(index) {
            if (index <= 0) {
                return;
            }
            const moved = this.items.splice(index, 1)[0];
            this.items.splice(index - 1, 0, moved);
        },

        moveDown(index) {
            if (index >= this.items.length - 1) {
                return;
            }
            const moved = this.items.splice(index, 1)[0];
            this.items.splice(index + 1, 0, moved);
        },

        submit() {
            if (this.submitted) {
                return;
            }
            this.submitted = true;
            this.$wire.submitOrder(Array.from(this.items));
        },
    };
}

export function registerOrdering(Alpine) {
    Alpine.data('orderingList', orderingList);
}
