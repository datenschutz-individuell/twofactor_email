# Pictures for the app store

Both are named in `appinfo/info.xml` and are fetched from GitHub by their raw URL, so
they must stay at these paths once a release names them.

| File | Size | Format | Read by |
|---|---|---|---|
| `challenge.png` | 855×479 | PNG | the store page, and every Nextcloud instance |
| `challenge.webp` | 356×200 | lossless webp | the store's app grid only |

Both files show the same picture and share a base name; only the format differs, and
`info.xml` names each one. The thumbnail is a 1:1 cut-out of the screenshot, not a scaled
copy: the grid container is 200 pixels high, so a picture of exactly that height is shown
pixel for pixel and stays readable.

**The formats are not interchangeable.** Only the screenshot passes Nextcloud's image
proxy, and that one refuses a lossless webp — hence PNG. The thumbnail is never proxied,
so it uses the smaller lossless webp. [releasing.md](../releasing.md) explains why.
