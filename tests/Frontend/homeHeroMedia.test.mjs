import assert from "node:assert/strict";
import { readFile, stat } from "node:fs/promises";
import test from "node:test";

const rootUrl = new URL("../../", import.meta.url);
const read = (path) => readFile(new URL(path, rootUrl), "utf8");

test("home hero uses responsive streamable video with an optimized poster", async () => {
    const navbar = await read("resources/js/components/layouts/Navbar.vue");

    assert.match(navbar, /autoplay muted loop playsinline/);
    assert.match(navbar, /webkit-playsinline preload="metadata"/);
    assert.match(navbar, /ai-pro-hero-mobile\.3dafc02a\.mp4/);
    assert.match(navbar, /media="\(max-width: 767px\)"/);
    assert.match(navbar, /ai-pro-hero\.91d4bdc1\.mp4/);
    assert.match(navbar, /ai-pro-hero\.7d4c3ca1\.webp/);
    assert.doesNotMatch(navbar, /IntersectionObserver/);
    assert.doesNotMatch(navbar, /setTimeout\([\s\S]{0,120}1800/);

    const videoPaths = [
        "public/video/ai-pro-hero.91d4bdc1.mp4",
        "public/video/ai-pro-hero-mobile.3dafc02a.mp4",
    ];

    for (const path of videoPaths) {
        const url = new URL(path, rootUrl);
        const [metadata, bytes] = await Promise.all([stat(url), readFile(url)]);
        const moovOffset = bytes.indexOf(Buffer.from("moov"));
        const mediaOffset = bytes.indexOf(Buffer.from("mdat"));

        assert.ok(metadata.size < 7 * 1024 * 1024, `${path} must remain below 7 MB`);
        assert.ok(moovOffset > 0 && moovOffset < mediaOffset, `${path} must use MP4 fast start`);
    }

    const mobileVideo = await stat(new URL(videoPaths[1], rootUrl));
    const poster = await stat(new URL("public/images/ai-pro-hero.7d4c3ca1.webp", rootUrl));
    assert.ok(mobileVideo.size < 3 * 1024 * 1024, "mobile video must remain below 3 MB");
    assert.ok(poster.size < 100 * 1024, "hero poster must remain below 100 KB");
});
