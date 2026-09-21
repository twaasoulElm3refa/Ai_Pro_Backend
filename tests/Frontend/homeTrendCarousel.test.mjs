import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";

const read = (path) => readFile(new URL(`../../${path}`, import.meta.url), "utf8");

test("home trend carousel is API-driven and opens the shared Chat7 route", async () => {
    const [home, service] = await Promise.all([
        read("resources/js/views/home/home.vue"),
        read("resources/js/services/home/homeService.js"),
    ]);

    assert.match(service, /fetchHomeTrendTools[\s\S]*api\.get\("\/home\/trend-tools"\)/);
    assert.match(home, /homeService\.fetchHomeTrendTools\(\)/);
    assert.match(home, /data\.tools\.map\(normalizeHomeTrendTool\)/);
    assert.match(home, /trendServices\.fetchProtectedImage\(tool\.previewUrl\)/);
    assert.match(home, /subtool\/\$\{tool\.slug\}\/chat7/);
    assert.match(home, /trendPageCount/);
    assert.match(home, /home-trends-dots/);
    assert.match(home, /class="home-trends-arrow is-previous"/);
    assert.match(home, /class="home-trends-arrow is-next"/);
    assert.match(home, /window\.setInterval[\s\S]*6000/);
    assert.match(home, /const\s+trendTools\s*=\s*ref\(\s*\[\s*\]\s*\)/);
    assert.doesNotMatch(home, /home-trend-fallback/);
});
