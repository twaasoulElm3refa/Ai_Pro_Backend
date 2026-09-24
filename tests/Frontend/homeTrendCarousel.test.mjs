import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";

const read = (path) => readFile(new URL(`../../${path}`, import.meta.url), "utf8");

test("home trend carousel is API-driven and opens the shared Chat7 route", async () => {
    const [home, service, apiClient] = await Promise.all([
        read("resources/js/views/home/home.vue"),
        read("resources/js/services/home/homeService.js"),
        read("resources/js/services/ApiClient.js"),
    ]);

    assert.match(service, /fetchHomeTrendTools[\s\S]*api\.get\("\/home\/trend-tools"\)/);
    assert.match(home, /homeService\.fetchHomeTrendTools\(\)/);
    assert.match(home, /data\.tools\.map\(normalizeHomeTrendTool\)/);
    assert.match(home, /imageUrl:\s*tool\.image\?\.preview_url/);
    assert.match(home, /loading="lazy"\s+decoding="async"/);
    assert.doesNotMatch(home, /trendServices\.fetchProtectedImage\(tool\.previewUrl\)/);
    assert.match(home, /subtool\/\$\{tool\.slug\}\/chat7/);
    assert.match(home, /trendPageCount/);
    assert.match(home, /home-trends-dots/);
    assert.match(home, /class="home-trends-arrow is-previous"/);
    assert.match(home, /class="home-trends-arrow is-next"/);
    assert.match(home, /@click="moveTrendPage\(-1\)"/);
    assert.match(home, /@click="moveTrendPage\(1\)"/);
    assert.match(home, /window\.innerWidth\s*<\s*768\s*\?\s*1\s*:\s*2/);
    assert.match(home, /class="home-trends-viewport"\s+:dir="isArabic\s*\?\s*'rtl'\s*:\s*'ltr'"/);
    assert.match(home, /v-for="item in trendCardsPerView"/);
    assert.match(home, /class="home-trends-page"/);
    assert.match(home, /grid-template-columns:\s*repeat\(var\(--trend-columns,\s*2\),\s*minmax\(0,\s*1fr\)\)/);
    assert.match(home, /aspect-ratio:\s*16\s*\/\s*9/);
    assert.match(home, /object-fit:\s*cover/);
    assert.match(home, /window\.setInterval[\s\S]*6000/);
    assert.match(home, /const\s+trendCarouselPages\s*=\s*computed/);
    assert.match(home, /scrollToTrendPhysicalPage\(trendPageCount\.value\)/);
    assert.match(home, /scheduleTrendLoopReset\(\)/);
    assert.match(home, /const\s+trendTools\s*=\s*ref\(\s*\[\s*\]\s*\)/);
    assert.doesNotMatch(home, /home-trend-fallback/);
    assert.match(apiClient, /import\.meta\.env\.VITE_API_KEY/);
    assert.match(apiClient, /import\.meta\.env\.VITE_API_BASE_URL/);
});
