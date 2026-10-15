// The share codec in wishlist.js must agree with src/ShareCodec.php.
// Run with: npm test
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

function load() {
    const noop = () => {};
    const window = { addEventListener: noop };
    const context = {
        window,
        document: { readyState: 'complete', addEventListener: noop, querySelectorAll: () => [], dispatchEvent: noop, documentElement: { setAttribute: noop } },
        localStorage: { getItem: () => null, setItem: noop },
        fetch: () => new Promise(noop),
        CustomEvent: class {},
        TextEncoder,
        TextDecoder,
        btoa,
        atob,
        crypto,
        setTimeout,
        WeakMap,
        URLSearchParams,
    };
    window.WishlistConfig = { driver: 'browser' };
    vm.createContext(context);
    vm.runInContext(readFileSync(new URL('./wishlist.js', import.meta.url), 'utf8'), context);
    return window.Wishlist;
}

const Wishlist = load();

test('encodes as the PHP codec does', () => {
    assert.equal(
        Wishlist.encode(['0b3e1c2a-9f4d-4e8b-a1c2-3d4e5f60718a', 'tags::linen', 'café::crème']),
        'Cz4cKp9NTouhwj1OX2Bxig.~dGFnczo6bGluZW4.~Y2Fmw6k6OmNyw6htZQ',
    );
});

test('decodes, dropping repeats and rubbish', () => {
    const a = Wishlist.encode(['0b3e1c2a-9f4d-4e8b-a1c2-3d4e5f60718a']);
    assert.deepEqual([...Wishlist.decode(`${a}.${a}.short.~!!!`)], ['0b3e1c2a-9f4d-4e8b-a1c2-3d4e5f60718a']);
    assert.deepEqual([...Wishlist.decode(Wishlist.encode(['home', 'tags::x']), 1)], ['home']);
});

test('the browser driver keeps lists in localStorage and builds share links', async () => {
    await Wishlist.ready;
    await Wishlist.add('0b3e1c2a-9f4d-4e8b-a1c2-3d4e5f60718a');
    await Wishlist.add('tags::linen', { data: { notes: 'Hi' } });

    assert.equal(Wishlist.count(), 2);
    assert.ok(Wishlist.has('tags::linen'));

    await Wishlist.toggle('tags::linen');
    assert.equal(Wishlist.has('tags::linen'), false);

    const url = Wishlist.shareUrl();
    assert.match(url, /^\/wishlist\/shared\?i=Cz4cKp9NTouhwj1OX2Bxig&t=My%20Wishlist$/);

    const gifts = (await Wishlist.createList('Gifts')).list;
    assert.equal(Wishlist.picked(), gifts.id, 'a new list is picked as it is made');
    await Wishlist.import(['home', 'home', 'tags::x'], gifts.id);
    assert.equal(Wishlist.count(gifts.id), 2);
    assert.equal(Wishlist.count('all'), 3);

    Wishlist.pick(Wishlist.list().id);
    await Wishlist.deleteList(Wishlist.list().id);
    assert.equal(Wishlist.picked(), gifts.id, 'deleting the picked list falls back to the default');
    assert.equal(Wishlist.list().id, gifts.id);
    assert.equal(Wishlist.list().default, true);
});
