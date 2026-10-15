// The share codec in wishlist.js must agree with src/ShareCodec.php.
// Run with: npm test
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

function load(stored = null) {
    const noop = () => {};
    const window = { addEventListener: noop };
    const context = {
        window,
        document: { readyState: 'complete', addEventListener: noop, querySelectorAll: () => [], dispatchEvent: noop, documentElement: { setAttribute: noop } },
        localStorage: { getItem: () => stored, setItem: noop },
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

test('corrupted localStorage is cleaned, not fatal', async () => {
    const cases = [
        'not json',
        '"a string"',
        '{"lists": "nope"}',
        '{"lists": [null, 1, "x", {"id": {}}, {"items": []}]}',
        JSON.stringify({ lists: [{ id: 'a', title: '<b>Gifts</b>', items: 'nope', default: 'yes' }] }),
        JSON.stringify({ lists: [{ id: 'a', items: [null, 'x', { id: ['x'] }, { id: 'ok', data: { notes: '<i>hi</i>', quantity: 'lots', evil: 'x', ['__proto__']: { hacked: true } } }, { id: 'ok' }] }] }),
        JSON.stringify({ lists: [{ id: 'a', default: true, items: [] }, { id: 'a', items: [] }, { id: 'b', default: true, items: [] }] }),
        JSON.stringify({ lists: Array.from({ length: 50 }, (_, n) => ({ id: 'l' + n, items: Array.from({ length: 600 }, (_, m) => ({ id: 'i' + m })) })) }),
    ];

    for (const stored of cases) {
        const W = load(stored);
        await W.ready;
        const lists = W.lists();
        assert.ok(Array.isArray(lists), stored);
        assert.ok(lists.length <= 20, 'list limit: ' + stored.slice(0, 40));
        lists.forEach((l) => {
            assert.equal(typeof l.id, 'string');
            assert.equal(typeof l.title, 'string');
            assert.ok(!/<|>/.test(l.title));
            assert.ok(Array.isArray(l.items) && l.items.length <= 500);
            l.items.forEach((i) => assert.equal(typeof i.id, 'string'));
        });
        if (lists.length) assert.equal(lists.filter((l) => l.default).length, 1, 'one default: ' + stored.slice(0, 60));
        // Everything still works on top of what was there.
        if (W.count() < 500) {
            await W.add('0b3e1c2a-9f4d-4e8b-a1c2-3d4e5f60718a');
            assert.ok(W.has('0b3e1c2a-9f4d-4e8b-a1c2-3d4e5f60718a'));
        } else {
            await assert.rejects(W.add('0b3e1c2a-9f4d-4e8b-a1c2-3d4e5f60718a'), /up to 500 items/);
        }
        assert.equal(W.count('all') >= 0, true);
    }

    const W = load(cases[5]);
    await W.ready;
    const item = W.list('a').items[0];
    assert.equal(W.list('a').items.length, 1);
    assert.deepEqual({ ...item.data }, { notes: 'hi', quantity: 1 });
    assert.equal({}.hacked, undefined);
    assert.equal(W.list('a').title, 'My Wishlist');

    const titled = load(cases[4]);
    await titled.ready;
    assert.equal(titled.list().title, 'Gifts');
});

test('the browser driver turns away odd references and keeps only item fields', async () => {
    const W = load();
    await W.ready;
    await assert.rejects(W.add('x'.repeat(300)));
    await W.add('tags::x', { data: { notes: 'Hi', colour: 'red', quantity: '0' } });
    assert.deepEqual({ ...W.list().items[0].data }, { notes: 'Hi', quantity: 1 });
    const result = await W.import([['nested'], {}, 'ok', 'ok', 42]);
    assert.equal(result.imported, 2);
});
