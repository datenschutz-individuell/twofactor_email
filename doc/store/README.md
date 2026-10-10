# Pictures for the app store

Both are named in `appinfo/info.xml` and are fetched from GitHub by their raw URL, so they must stay at these paths once a release names them.

| File | Size | Format | Read by |
|---|---|---|---|
| `screenshot.webp` | 855×479 | lossless webp | the store page, and every Nextcloud instance |
| `thumbnail.webp` | 356×200 | lossless webp | the store's app grid only |

The thumbnail is a 1:1 cut-out of the screenshot, not a scaled copy: the grid container is 200 pixels high, so a picture of exactly that height is shown pixel for pixel and stays readable.

**A changed picture needs a new file name.** Instances get the screenshot through a proxy that fetches each URL only once. Keep the old file until the release that names the new one is published. [releasing.md](../releasing.md) explains the proxy.
