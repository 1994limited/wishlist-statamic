import Index from './pages/Index.vue';
import Show from './pages/Show.vue';

Statamic.booting(() => {
    Statamic.$inertia.register('wishlist::Index', Index);
    Statamic.$inertia.register('wishlist::Show', Show);
});
