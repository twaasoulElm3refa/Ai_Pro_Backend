const absoluteHttpUrl = /^https?:\/\//i;

const pathnameFromBaseUrl = (baseUrl) => {
    const value = String(baseUrl || "").trim();

    if (!value) return "";

    if (absoluteHttpUrl.test(value)) {
        try {
            return new URL(value).pathname.replace(/\/+$/, "");
        } catch {
            return "";
        }
    }

    return `/${value.replace(/^\/+|\/+$/g, "")}`;
};

/**
 * Convert an API-prefixed relative URL into a URL relative to Axios' baseURL.
 * Absolute URLs remain untouched because Axios does not prepend its baseURL to them.
 */
export const toApiRequestUrl = (url, baseUrl = "/api/v1") => {
    const value = String(url || "").trim();

    if (!value || absoluteHttpUrl.test(value)) {
        return value;
    }

    const requestPath = `/${value.replace(/^\/+/, "")}`;
    const basePath = pathnameFromBaseUrl(baseUrl);

    if (!basePath || basePath === "/") {
        return requestPath;
    }

    if (requestPath === basePath) {
        return "/";
    }

    if (requestPath.startsWith(`${basePath}/`)) {
        return requestPath.slice(basePath.length);
    }

    return requestPath;
};
