import { reactive } from 'vue';

const state = reactive({
    items: [],
});

let nextId = 1;

export function useToast() {
    return {
        items: state.items,
        push(message, tone = 'success') {
            const id = nextId++;

            state.items.push({ id, message, tone });

            window.setTimeout(() => {
                const index = state.items.findIndex((item) => item.id === id);

                if (index !== -1) {
                    state.items.splice(index, 1);
                }
            }, 4500);

            return id;
        },
        dismiss(id) {
            const index = state.items.findIndex((item) => item.id === id);

            if (index !== -1) {
                state.items.splice(index, 1);
            }
        },
    };
}
