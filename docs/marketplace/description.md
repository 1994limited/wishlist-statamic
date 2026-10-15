**Wishlist lets visitors save entries to wishlists, keep as many named lists as they like, and share any of them with a link.**

Keep lists in flat files, in the database, or only in the visitor's own browser, so nothing about them is stored on your server. Your templates are the same whichever you choose.

## Save anything

Products, recipes, properties, events: any entry, in the collections you choose, and terms too if you like. Visitors can keep a note and a quantity with each item: who it's for, which size, why they wanted it.

## Several lists

"Birthday", "Kitchen ideas", "For later". Visitors make, rename, delete and pick between their lists, with a default for quick saves.

## Share links

Every list has a read-only page in your own layout, with a **Save these to my wishlist** button for whoever opens it. Links can be reset.

## Three storage drivers

- **Files**, indexed like entries
- **Database**, in two tables
- **Browser**, in `localStorage`, with the items carried in the share link

Move between files and the database with one command.

## Guests welcome

Visitors don't need an account. When they sign in, what they saved as a guest moves to their account.

## Works without JavaScript

Every button is a real form. With the script (no dependencies, no build step) the page updates in place, and it works on statically cached pages.

## Your markup

Tags and `data-` attributes, with no imposed components or styles. Lists are drawn with your own partials.

## The Control Panel

**Content → Wishlists** shows every list, what's on it, and the entries saved most, with permissions to view and delete.

## For developers

Events, a PHP API, a JSON API for headless front ends, and room for your own storage driver.

## Requirements

- Statamic 6
- PHP 8.3 or later
- **Statamic Pro** for front-end user accounts and Control Panel permissions. Guests can keep lists without it.
