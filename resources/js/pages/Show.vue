<!--
    One wishlist: whose it is, its share link, and what's on it.
-->
<script>
import { Head, Link, router } from '@statamic/cms/inertia';
import { Badge, Button, Header, Input, Panel, Table, TableCell, TableColumn, TableColumns, TableRow, TableRows } from '@statamic/cms/ui';
import icon from '../../brand/nav-icon.svg?raw';

export default {
    components: { Badge, Button, Head, Header, Input, Link, Panel, Table, TableCell, TableColumn, TableColumns, TableRow, TableRows },

    computed: {
        icon: () => icon,
    },

    props: {
        list: { type: Object, required: true },
        items: { type: Array, required: true },
        canDelete: { type: Boolean, default: false },
        urls: { type: Object, required: true },
    },

    methods: {
        destroy() {
            if (!confirm(__('Delete “:list”? Its share link will stop working.', { list: this.list.title }))) {
                return;
            }

            router.delete(this.urls.destroy);
        },

        details(data) {
            return Object.entries(data || {})
                .map(([key, value]) => `${key}: ${value}`)
                .join(' · ');
        },
    },
};
</script>

<template>
    <Head :title="list.title" />

    <div class="mx-auto max-w-5xl">
        <div class="mb-2 text-sm">
            <Link :href="urls.index" class="text-gray-500 hover:underline!">← {{ __('Wishlists') }}</Link>
        </div>

        <Header :title="list.title" :icon="icon">
            <Button v-if="canDelete" variant="danger" :text="__('Delete')" @click="destroy" />
        </Header>

        <Panel class="mb-6">
            <dl class="grid gap-4 p-4 text-sm sm:grid-cols-4">
                <div>
                    <dt class="text-gray-500">{{ __('Owner') }}</dt>
                    <dd>
                        <a v-if="list.owner.url" :href="list.owner.url" class="hover:underline!">{{ list.owner.name }}</a>
                        <Badge v-else :text="list.owner.name" size="sm" />
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500">{{ __('Items') }}</dt>
                    <dd class="tabular-nums">{{ list.count }}<span v-if="list.is_default" class="text-gray-500"> · {{ __('default list') }}</span></dd>
                </div>
                <div>
                    <dt class="text-gray-500">{{ __('Created') }}</dt>
                    <dd>{{ list.created_at || '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">{{ __('Updated') }}</dt>
                    <dd>{{ list.updated_at || '—' }}</dd>
                </div>
                <div v-if="list.share_url" class="sm:col-span-4">
                    <dt class="mb-1 text-gray-500">{{ __('Share link') }}</dt>
                    <dd><Input :model-value="list.share_url" read-only copyable /></dd>
                </div>
            </dl>
        </Panel>

        <Panel>
            <Table v-if="items.length">
                <TableColumns>
                    <TableColumn>{{ __('Item') }}</TableColumn>
                    <TableColumn>{{ __('From') }}</TableColumn>
                    <TableColumn>{{ __('Kept with it') }}</TableColumn>
                    <TableColumn class="text-right">{{ __('Added') }}</TableColumn>
                </TableColumns>
                <TableRows>
                    <TableRow v-for="item in items" :key="item.id">
                        <TableCell>
                            <a v-if="item.edit_url" :href="item.edit_url" class="font-medium hover:underline!">{{ item.title }}</a>
                            <span v-else class="text-gray-500">{{ __('Deleted') }} <code class="text-xs">{{ item.id }}</code></span>
                        </TableCell>
                        <TableCell class="text-gray-500">{{ item.kind || '—' }}</TableCell>
                        <TableCell class="text-gray-500">{{ details(item.data) || '—' }}</TableCell>
                        <TableCell class="text-right text-gray-500">{{ item.added_at }}</TableCell>
                    </TableRow>
                </TableRows>
            </Table>
            <p v-else class="p-6 text-center text-sm text-gray-500">{{ __('This list is empty.') }}</p>
        </Panel>
    </div>
</template>
