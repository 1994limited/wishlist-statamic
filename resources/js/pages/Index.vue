<!--
    Content → Wishlists: every visitor's lists, newest change first, with a
    search on title or owner, and the entries saved most often.
-->
<script>
import { Head, Link, router } from '@statamic/cms/inertia';
import { Alert, Badge, Header, Heading, Input, Panel, Subheading, Table, TableCell, TableColumn, TableColumns, TableRow, TableRows } from '@statamic/cms/ui';

export default {
    components: { Alert, Badge, Head, Header, Heading, Input, Link, Panel, Subheading, Table, TableCell, TableColumn, TableColumns, TableRow, TableRows },

    props: {
        driver: { type: String, required: true },
        storesOnServer: { type: Boolean, required: true },
        search: { type: String, default: null },
        lists: { type: Array, required: true },
        pagination: { type: Object, required: true },
        popular: { type: Array, required: true },
        urls: { type: Object, required: true },
    },

    data() {
        return { query: this.search ?? '', timer: null };
    },

    watch: {
        query(query) {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => {
                router.get(this.urls.index, query ? { search: query } : {}, { preserveState: true, replace: true });
            }, 300);
        },
    },

    methods: {
        pageUrl(page) {
            const params = new URLSearchParams();
            if (this.search) params.set('search', this.search);
            if (page > 1) params.set('page', page);
            const query = params.toString();
            return this.urls.index + (query ? `?${query}` : '');
        },
    },
};
</script>

<template>
    <Head :title="__('Wishlists')" />

    <div class="mx-auto max-w-5xl">
        <Header :title="__('Wishlists')" icon="favorite-heart" />

        <Alert
            v-if="!storesOnServer"
            variant="default"
            :heading="__('Wishlists are kept in visitors’ browsers')"
            :text="__('This site uses the browser driver, so lists live in each visitor’s localStorage and share links carry the items in the URL. There is nothing stored here to show. Set WISHLIST_DRIVER to file or database to keep lists on the server.')"
        />

        <template v-else>
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    <div class="mb-3">
                        <Input v-model="query" icon="magnifying-glass" :placeholder="__('Search by title or owner')" clearable />
                    </div>

                    <Panel>
                        <Table v-if="lists.length">
                            <TableColumns>
                                <TableColumn>{{ __('List') }}</TableColumn>
                                <TableColumn>{{ __('Owner') }}</TableColumn>
                                <TableColumn class="text-right">{{ __('Items') }}</TableColumn>
                                <TableColumn class="text-right">{{ __('Updated') }}</TableColumn>
                            </TableColumns>
                            <TableRows>
                                <TableRow v-for="list in lists" :key="list.id">
                                    <TableCell>
                                        <Link :href="list.url" class="font-medium hover:underline!">{{ list.title }}</Link>
                                    </TableCell>
                                    <TableCell>
                                        <a v-if="list.owner.url" :href="list.owner.url" class="hover:underline!">{{ list.owner.name }}</a>
                                        <Badge v-else :text="list.owner.name" size="sm" />
                                    </TableCell>
                                    <TableCell class="text-right tabular-nums">{{ list.count }}</TableCell>
                                    <TableCell class="text-right text-gray-500" :title="list.updated_at">{{ list.updated_ago }}</TableCell>
                                </TableRow>
                            </TableRows>
                        </Table>

                        <p v-else class="p-6 text-center text-sm text-gray-500">
                            {{ search ? __('No wishlists match “:search”.', { search }) : __('Nobody has saved anything yet.') }}
                        </p>
                    </Panel>

                    <nav v-if="pagination.last_page > 1" class="mt-4 flex items-center justify-between text-sm">
                        <Link v-if="pagination.page > 1" :href="pageUrl(pagination.page - 1)" class="hover:underline!">← {{ __('Newer') }}</Link>
                        <span v-else />
                        <span class="text-gray-500">{{ __(':page of :last', { page: pagination.page, last: pagination.last_page }) }} · {{ __(':total lists', { total: pagination.total }) }}</span>
                        <Link v-if="pagination.page < pagination.last_page" :href="pageUrl(pagination.page + 1)" class="hover:underline!">{{ __('Older') }} →</Link>
                        <span v-else />
                    </nav>
                </div>

                <Panel :heading="__('Most saved')">
                    <ol v-if="popular.length" class="divide-y divide-gray-200 dark:divide-gray-700!">
                        <li v-for="item in popular" :key="item.id" class="flex items-baseline justify-between gap-3 px-4 py-2 text-sm">
                            <span class="min-w-0 truncate">
                                <a v-if="item.edit_url" :href="item.edit_url" class="hover:underline!">{{ item.title }}</a>
                                <span v-else class="text-gray-500">{{ item.missing ? __('Deleted') : item.title }}</span>
                            </span>
                            <span class="shrink-0 tabular-nums text-gray-500">{{ item.count }}</span>
                        </li>
                    </ol>
                    <p v-else class="px-4 py-3 text-sm text-gray-500">{{ __('Nothing yet.') }}</p>
                </Panel>
            </div>
        </template>
    </div>
</template>
