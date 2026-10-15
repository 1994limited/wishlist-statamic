/*
 * Wishlist for Statamic: the front-end script.
 *
 * Added to a page by {{ wishlist:scripts }}. Gives window.Wishlist, the
 * same API whichever driver the site uses:
 *
 *   - file / database: lists are on the server; this talks to /!/wishlist.
 *   - browser: lists are in this browser's localStorage, and share links
 *     carry the items in the URL.
 *
 * And wires up the page, so most sites need no JavaScript of their own:
 *
 *   form[data-wishlist-form]    the forms {{ wishlist:toggle }} etc. render,
 *                               sent with fetch() (or kept in the browser)
 *   [data-wishlist-toggle="id"] any button: adds or removes the item;
 *                               aria-pressed follows
 *   [data-wishlist-when="added" | "not-added"]
 *                               inside either of those: shown only then
 *   [data-wishlist-count="" | "list id" | "all"]
 *                               text set to the number of items
 *   [data-wishlist-share="" | "list id"]
 *                               a link's href, an input's value, or text,
 *                               set to the list's share link
 *   [data-wishlist-copy-share]  a button that copies the share link
 *   [data-wishlist-render]      redrawn with your partial when lists change;
 *                               without a data-wishlist-list of its own it
 *                               draws the picked list
 *   select[data-wishlist-lists] filled with the visitor's lists; picks which
 *                               list is drawn, shared and acted on by list
 *                               forms that don't name one. A new list is
 *                               picked as it's made
 *
 * Everything that changes fires "wishlist:change" on document, with
 * { lists } as its detail; failures fire "wishlist:error" with { message }.
 */
(function () {
    'use strict';

    if (window.Wishlist) {
        return;
    }

    var config = Object.assign(
        {
            driver: 'file',
            endpoint: '/!/wishlist',
            shareUrl: '/wishlist/shared',
            storageKey: 'statamic.wishlist',
            defaultTitle: 'My Wishlist',
            limits: { lists: 20, items: 500 },
            maxShareItems: 200,
        },
        window.WishlistConfig || {}
    );

    var listeners = [];

    /*
     * Share codec. Must agree with src/ShareCodec.php: references joined
     * with ".", a UUID as its 16 bytes in base64url, anything else as "~"
     * and the base64url of its UTF-8 text.
     */
    var UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

    function bytesToBase64url(bytes) {
        var binary = '';
        for (var i = 0; i < bytes.length; i++) binary += String.fromCharCode(bytes[i]);
        return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }

    function base64urlToBytes(text) {
        if (!/^[A-Za-z0-9_-]*$/.test(text)) return null;
        var padded = text.replace(/-/g, '+').replace(/_/g, '/');
        while (padded.length % 4) padded += '=';
        try {
            var binary = atob(padded);
        } catch (e) {
            return null;
        }
        var bytes = new Uint8Array(binary.length);
        for (var i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
        return bytes;
    }

    function encode(references) {
        return references
            .map(function (reference) {
                reference = String(reference);
                if (UUID.test(reference)) {
                    var hex = reference.replace(/-/g, '');
                    var bytes = new Uint8Array(16);
                    for (var i = 0; i < 16; i++) bytes[i] = parseInt(hex.substr(i * 2, 2), 16);
                    return bytesToBase64url(bytes);
                }
                return '~' + bytesToBase64url(new TextEncoder().encode(reference));
            })
            .join('.');
    }

    function decode(encoded, limit) {
        var references = [];
        (encoded || '').split('.').forEach(function (token) {
            if (!token || (limit && references.length >= limit)) return;
            var reference = null;
            if (token.charAt(0) === '~') {
                var bytes = base64urlToBytes(token.slice(1));
                if (bytes) {
                    try {
                        reference = new TextDecoder('utf-8', { fatal: true }).decode(bytes);
                    } catch (e) {
                        reference = null;
                    }
                }
            } else if (token.length === 22) {
                var uuid = base64urlToBytes(token);
                if (uuid && uuid.length === 16) {
                    var hex = Array.prototype.map
                        .call(uuid, function (b) {
                            return ('0' + b.toString(16)).slice(-2);
                        })
                        .join('');
                    reference = [hex.slice(0, 8), hex.slice(8, 12), hex.slice(12, 16), hex.slice(16, 20), hex.slice(20)].join('-');
                }
            }
            if (reference && references.indexOf(reference) === -1) references.push(reference);
        });
        return references;
    }

    function uuid() {
        if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = (Math.random() * 16) | 0;
            return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
        });
    }

    function fail(message) {
        var error = new Error(message);
        error.wishlist = true;
        throw error;
    }

    /*
     * The browser driver: lists in localStorage.
     */
    function BrowserStore() {
        this.state = { lists: [] };
    }

    BrowserStore.prototype.load = function () {
        try {
            var stored = JSON.parse(localStorage.getItem(config.storageKey) || 'null');
            this.state = stored && Array.isArray(stored.lists) ? stored : { lists: [] };
        } catch (e) {
            this.state = { lists: [] };
        }
        return Promise.resolve(this.state.lists);
    };

    BrowserStore.prototype.write = function () {
        try {
            localStorage.setItem(config.storageKey, JSON.stringify({ version: 1, lists: this.state.lists }));
        } catch (e) {
            // Private browsing or full storage: keep going for this page.
        }
        return this.state.lists;
    };

    BrowserStore.prototype.find = function (listId) {
        var lists = this.state.lists;
        if (!listId || listId === 'default') {
            return lists.filter(function (l) { return l.default; })[0] || lists[0] || null;
        }
        return lists.filter(function (l) { return l.id === listId; })[0] || null;
    };

    BrowserStore.prototype.findOrCreate = function (listId) {
        var list = this.find(listId);
        if (list) return list;
        if (listId && listId !== 'default') fail('That wishlist could not be found.');
        list = { id: uuid(), title: config.defaultTitle, default: true, items: [], created_at: new Date().toISOString() };
        this.state.lists.unshift(list);
        return list;
    };

    BrowserStore.prototype.run = function (action, payload) {
        var store = this;
        try {
            var result = store[action](payload || {});
            return Promise.resolve(Object.assign({ lists: store.write() }, result || {}));
        } catch (e) {
            return Promise.reject(e);
        }
    };

    BrowserStore.prototype.add = function (p) {
        var list = this.findOrCreate(p.list);
        var existing = list.items.filter(function (i) { return i.id === p.id; })[0];
        if (existing) {
            if (p.data) existing.data = Object.assign(existing.data || {}, p.data);
        } else {
            if (list.items.length >= config.limits.items) fail('A wishlist can hold up to ' + config.limits.items + ' items.');
            var item = { id: String(p.id), added_at: new Date().toISOString() };
            if (p.data && Object.keys(p.data).length) item.data = p.data;
            list.items.push(item);
        }
        return { added: true, list: list };
    };

    BrowserStore.prototype.remove = function (p) {
        var list = this.find(p.list);
        if (!list) fail('That wishlist could not be found.');
        list.items = list.items.filter(function (i) { return i.id !== String(p.id); });
        return { added: false, list: list };
    };

    BrowserStore.prototype.toggle = function (p) {
        var list = this.find(p.list);
        var on = list && list.items.some(function (i) { return i.id === String(p.id); });
        return on ? this.remove(p) : this.add(p);
    };

    BrowserStore.prototype.clear = function (p) {
        var list = this.find(p.list);
        if (!list) fail('That wishlist could not be found.');
        list.items = [];
        return { list: list };
    };

    BrowserStore.prototype.create = function (p) {
        if (this.state.lists.length >= config.limits.lists) fail('You can keep up to ' + config.limits.lists + ' wishlists.');
        var list = {
            id: uuid(),
            title: (p.title || '').trim().slice(0, 100) || config.defaultTitle,
            default: this.state.lists.length === 0,
            items: [],
            created_at: new Date().toISOString(),
        };
        this.state.lists.push(list);
        return { list: list };
    };

    BrowserStore.prototype.rename = function (p) {
        var list = this.find(p.list);
        if (!list) fail('That wishlist could not be found.');
        list.title = (p.title || '').trim().slice(0, 100) || config.defaultTitle;
        return { list: list };
    };

    BrowserStore.prototype['delete'] = function (p) {
        var list = this.find(p.list);
        if (!list) fail('That wishlist could not be found.');
        this.state.lists = this.state.lists.filter(function (l) { return l !== list; });
        if (list.default && this.state.lists[0]) this.state.lists[0].default = true;
        return { list: null };
    };

    BrowserStore.prototype.makeDefault = function (p) {
        var list = this.find(p.list);
        if (!list) fail('That wishlist could not be found.');
        this.state.lists.forEach(function (l) { l.default = l === list; });
        return { list: list };
    };

    BrowserStore.prototype.import = function (p) {
        var list = this.findOrCreate(p.list);
        var added = 0;
        (p.ids || []).forEach(function (id) {
            if (list.items.length >= config.limits.items) return;
            if (list.items.some(function (i) { return i.id === id; })) return;
            list.items.push({ id: id, added_at: new Date().toISOString() });
            added++;
        });
        return { list: list, imported: added };
    };

    /*
     * The file and database drivers: lists on the server.
     */
    function ServerStore() {
        this.state = { lists: [], csrf: null };
    }

    ServerStore.prototype.load = function () {
        var store = this;
        return fetch(config.endpoint + '/state', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (response) { return response.json(); })
            .then(function (state) {
                store.state = state;
                return state.lists;
            });
    };

    ServerStore.prototype.find = BrowserStore.prototype.find;

    var routes = {
        add: 'items/add',
        remove: 'items/remove',
        toggle: 'items/toggle',
        clear: 'lists/clear',
        create: 'lists/create',
        rename: 'lists/rename',
        delete: 'lists/delete',
        makeDefault: 'lists/default',
        resetShare: 'lists/share',
        import: 'lists/import',
    };

    ServerStore.prototype.run = function (action, payload) {
        var store = this;
        return fetch(config.endpoint + '/' + routes[action], {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': store.state.csrf || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload || {}),
        }).then(function (response) {
            return response.json().then(function (body) {
                if (!response.ok || body.success === false) fail(body.message || 'Something went wrong.');
                store.state.lists = body.lists;
                return body;
            });
        });
    };

    var store = config.driver === 'browser' ? new BrowserStore() : new ServerStore();

    function lists() {
        return store.state.lists || [];
    }

    function list(listId) {
        return store.find(listId);
    }

    function has(id, listId) {
        id = String(id);
        if (listId === 'any') {
            return lists().some(function (l) {
                return l.items.some(function (i) { return i.id === id; });
            });
        }
        var l = list(listId);
        return !!l && l.items.some(function (i) { return i.id === id; });
    }

    function count(listId) {
        if (listId === 'all') {
            return lists().reduce(function (sum, l) { return sum + l.items.length; }, 0);
        }
        var l = list(listId);
        return l ? l.items.length : 0;
    }

    function shareUrl(listId) {
        var l = list(listId);
        if (!l || !config.shareUrl) return null;
        if (config.driver !== 'browser') return l.share_url || null;
        var references = l.items.slice(0, config.maxShareItems).map(function (i) { return i.id; });
        return config.shareUrl + '?i=' + encode(references) + '&t=' + encodeURIComponent(l.title);
    }

    function changed(result) {
        var detail = { lists: lists(), result: result };
        listeners.forEach(function (fn) { fn(detail); });
        document.dispatchEvent(new CustomEvent('wishlist:change', { detail: detail }));
        refresh();
        return result;
    }

    function failed(error) {
        var message = (error && error.message) || 'Something went wrong.';
        document.dispatchEvent(new CustomEvent('wishlist:error', { detail: { message: message } }));
        throw error;
    }

    // The list picked in a [data-wishlist-lists] select, which list
    // forms and drawn lists follow. A new list is picked as it's made.
    var picked = null;

    function pickedList() {
        var l = (picked && list(picked)) || list();
        return l ? l.id : undefined;
    }

    function run(action, payload) {
        return ready.then(function () {
            return store
                .run(action, payload)
                .then(function (result) {
                    if (action === 'create' && result && result.list) picked = result.list.id;
                    return result;
                })
                .then(changed, failed);
        });
    }

    var Wishlist = {
        config: config,
        encode: encode,
        decode: decode,
        lists: lists,
        list: list,
        has: has,
        count: count,
        shareUrl: shareUrl,
        /** The list picked in a [data-wishlist-lists] select (or the default). */
        picked: function () {
            return pickedList();
        },
        pick: function (listId) {
            picked = listId;
            refresh();
        },
        add: function (id, options) {
            options = options || {};
            return run('add', { id: String(id), list: options.list, data: options.data });
        },
        remove: function (id, options) {
            return run('remove', { id: String(id), list: (options || {}).list });
        },
        toggle: function (id, options) {
            options = options || {};
            return run('toggle', { id: String(id), list: options.list, data: options.data });
        },
        clear: function (listId) {
            return run('clear', { list: listId });
        },
        createList: function (title) {
            return run('create', { title: title });
        },
        renameList: function (listId, title) {
            return run('rename', { list: listId, title: title });
        },
        deleteList: function (listId) {
            return run('delete', { list: listId });
        },
        makeDefault: function (listId) {
            return run('makeDefault', { list: listId });
        },
        /** File and database drivers: a new share link; old ones stop working. */
        resetShareLink: function (listId) {
            if (config.driver === 'browser') return Promise.resolve({ lists: lists() });
            return run('resetShare', { list: listId });
        },
        /** Add references (or an encoded string) to a list, skipping repeats. */
        import: function (references, listId) {
            var ids = typeof references === 'string' ? decode(references, config.maxShareItems) : references.map(String);
            return run('import', { ids: ids, list: listId });
        },
        /** Each reference's fields (wishlist.resolve_fields) from the server. */
        resolve: function (references) {
            var ids = references.map(String);
            if (!ids.length) return Promise.resolve([]);
            return fetch(config.endpoint + '/resolve?i=' + encode(ids), { headers: { Accept: 'application/json' } })
                .then(function (response) { return response.json(); })
                .then(function (body) { return body.items; });
        },
        on: function (fn) {
            listeners.push(fn);
            return function () {
                listeners = listeners.filter(function (l) { return l !== fn; });
            };
        },
        reload: function () {
            return store.load().then(function () { return changed(null); });
        },
    };

    /*
     * The page.
     */
    // The styles from {{ wishlist:scripts }} show the right label from
    // data-wishlist-active. This also clears a `hidden` a template set, for
    // templates written before those styles existed.
    function setWhen(root, added) {
        root.querySelectorAll('[data-wishlist-when]').forEach(function (el) {
            var when = el.getAttribute('data-wishlist-when');
            el.hidden = when === 'added' ? !added : when === 'not-added' ? added : el.hidden;
        });
    }

    function refresh() {
        document.querySelectorAll('[data-wishlist-toggle]').forEach(function (el) {
            var added = has(el.getAttribute('data-wishlist-toggle'), el.getAttribute('data-wishlist-list') || undefined);
            el.setAttribute('aria-pressed', added ? 'true' : 'false');
            el.setAttribute('data-wishlist-active', added ? 'true' : 'false');
            setWhen(el, added);
        });

        document.querySelectorAll('form[data-wishlist-form][data-wishlist-id]').forEach(function (form) {
            var listField = form.querySelector('input[name="list"]');
            var added = has(form.getAttribute('data-wishlist-id'), listField ? listField.value : undefined);
            form.setAttribute('data-wishlist-active', added ? 'true' : 'false');
            form.querySelectorAll('[type="submit"], button:not([type])').forEach(function (button) {
                button.setAttribute('aria-pressed', added ? 'true' : 'false');
            });
            setWhen(form, added);
        });

        document.querySelectorAll('[data-wishlist-count]').forEach(function (el) {
            el.textContent = count(el.getAttribute('data-wishlist-count') || undefined);
        });

        document.querySelectorAll('select[data-wishlist-lists]').forEach(fillPicker);

        document.querySelectorAll('[data-wishlist-share]').forEach(function (el) {
            var url = shareUrl(el.getAttribute('data-wishlist-share') || pickedList()) || '';
            if (el.tagName === 'A') el.href = url || '#';
            else if ('value' in el && el.tagName !== 'BUTTON') el.value = url;
            else el.textContent = url;
        });

        document.querySelectorAll('[data-wishlist-render]').forEach(render);
    }

    function fillPicker(select) {
        var current = pickedList();
        select.innerHTML = '';
        lists().forEach(function (l) {
            var option = document.createElement('option');
            option.value = l.id;
            option.textContent = l.title + ' (' + l.items.length + ')';
            option.selected = l.id === current;
            select.appendChild(option);
        });
        select.disabled = lists().length === 0;
    }

    var rendering = new WeakMap();

    function renderUrl(container) {
        var l = list(container.getAttribute('data-wishlist-list') || pickedList());
        var references = l ? l.items.map(function (i) { return i.id; }) : [];
        return (
            config.endpoint +
            '/render?view=' + encodeURIComponent(container.getAttribute('data-wishlist-render')) +
            '&i=' + encode(references) +
            (l ? '&t=' + encodeURIComponent(l.title) : '')
        );
    }

    function render(container) {
        var url = renderUrl(container);

        if (rendering.get(container) === url) return;
        rendering.set(container, url);

        fetch(url, { headers: { Accept: 'text/html' } })
            .then(function (response) { return response.ok ? response.text() : null; })
            .then(function (html) {
                if (html !== null && rendering.get(container) === url) {
                    container.innerHTML = html;
                    refresh();
                }
            });
    }

    function formPayload(form) {
        var data = new FormData(form);
        var payload = { data: {} };
        data.forEach(function (value, name) {
            if (name === '_token' || name.charAt(0) === '_') return;
            if (name === 'id' || name === 'list' || name === 'title' || name === 'key' || name === 'i') payload[name] = value;
            else if (name.indexOf('data[') === 0) payload.data[name.slice(5, -1)] = value;
            else payload.data[name] = value;
        });

        // A list form with no list of its own acts on the picked list.
        if (!payload.list && form.getAttribute('data-wishlist-form').indexOf('lists/') === 0) {
            payload.list = pickedList();
        }

        return payload;
    }

    var formActions = {
        toggle: 'toggle',
        add: 'add',
        remove: 'remove',
        'lists/create': 'create',
        'lists/rename': 'rename',
        'lists/delete': 'delete',
        'lists/clear': 'clear',
        'lists/default': 'makeDefault',
        'lists/share': 'resetShare',
        import: 'import',
    };

    document.addEventListener('submit', function (event) {
        var form = event.target.closest && event.target.closest('form[data-wishlist-form]');
        if (!form || !formActions[form.getAttribute('data-wishlist-form')]) return;

        var action = formActions[form.getAttribute('data-wishlist-form')];
        var payload = formPayload(form);

        // A shared page's "save these" form: the browser driver copies the
        // items from the link; the server ones copy by key.
        if (action === 'import' && config.driver === 'browser') {
            payload = { ids: decode(payload.i || '', config.maxShareItems), list: payload.list };
        }

        event.preventDefault();
        form.setAttribute('aria-busy', 'true');
        form.removeAttribute('data-wishlist-error');

        run(action, payload)
            .then(function () {
                if (action === 'create' || action === 'rename') form.reset();
            })
            .catch(function (error) {
                form.setAttribute('data-wishlist-error', error.message);
            })
            .then(function () {
                form.removeAttribute('aria-busy');
            });
    });

    document.addEventListener('change', function (event) {
        if (event.target.matches && event.target.matches('select[data-wishlist-lists]')) {
            picked = event.target.value;
            refresh();
        }
    });

    document.addEventListener('click', function (event) {
        var toggle = event.target.closest && event.target.closest('[data-wishlist-toggle]');
        if (toggle) {
            event.preventDefault();
            Wishlist.toggle(toggle.getAttribute('data-wishlist-toggle'), {
                list: toggle.getAttribute('data-wishlist-list') || undefined,
            }).catch(function () {});
            return;
        }

        var copy = event.target.closest && event.target.closest('[data-wishlist-copy-share]');
        if (copy) {
            event.preventDefault();
            var url = shareUrl(copy.getAttribute('data-wishlist-copy-share') || pickedList());
            if (url && navigator.clipboard) {
                navigator.clipboard.writeText(url).then(function () {
                    copy.setAttribute('data-wishlist-copied', 'true');
                    setTimeout(function () { copy.removeAttribute('data-wishlist-copied'); }, 2000);
                });
            }
        }
    });

    // Another tab changed the lists.
    window.addEventListener('storage', function (event) {
        if (config.driver === 'browser' && event.key === config.storageKey) {
            store.load().then(function () { changed(null); });
        }
    });

    var ready = store.load().catch(function () { return []; });

    Wishlist.ready = ready.then(function () { return Wishlist; });

    window.Wishlist = Wishlist;

    function start() {
        ready.then(function () {
            // The server drivers rendered these already; draw them again
            // only once the list changes.
            if (config.driver !== 'browser') {
                document.querySelectorAll('[data-wishlist-render]').forEach(function (container) {
                    rendering.set(container, renderUrl(container));
                });
            }
            refresh();
            document.dispatchEvent(new CustomEvent('wishlist:ready', { detail: { lists: lists() } }));
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
    else start();
})();
