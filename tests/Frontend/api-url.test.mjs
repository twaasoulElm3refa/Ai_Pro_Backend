import assert from "node:assert/strict";
import test from "node:test";
import { toApiRequestUrl } from "../../resources/js/utils/apiUrl.js";

test("removes an API prefix already supplied by the Axios base URL", () => {
    const fileUrl = "/api/v1/generated-images/file-id/download";

    assert.equal(
        toApiRequestUrl(fileUrl, "/api/v1"),
        "/generated-images/file-id/download"
    );
    assert.equal(
        toApiRequestUrl(fileUrl, "https://pro.aiarabic.com/api/v1"),
        "/generated-images/file-id/download"
    );
});

test("keeps base-relative and absolute generated file URLs stable", () => {
    assert.equal(
        toApiRequestUrl("/generated-images/file-id/download", "/api/v1"),
        "/generated-images/file-id/download"
    );
    assert.equal(
        toApiRequestUrl(
            "https://pro.aiarabic.com/api/v1/generated-images/file-id/download",
            "/api/v1"
        ),
        "https://pro.aiarabic.com/api/v1/generated-images/file-id/download"
    );
});

test("does not strip similar but different API version prefixes", () => {
    assert.equal(
        toApiRequestUrl("/api/v10/generated-images/file-id/download", "/api/v1"),
        "/api/v10/generated-images/file-id/download"
    );
});
