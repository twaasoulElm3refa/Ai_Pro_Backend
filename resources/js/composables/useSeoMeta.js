import { computed, unref, watchEffect } from "vue";

const getMetaTag = (name) => {
    let tag = document.querySelector(`meta[name="${name}"]`);

    if (!tag) {
        tag = document.createElement("meta");
        tag.setAttribute("name", name);
        document.head.appendChild(tag);
    }

    return tag;
};

export default function useSeoMeta(options = {}) {
    const title = computed(() => unref(options.title) || "");
    const description = computed(() => unref(options.description) || "");
    const keywords = computed(() => unref(options.keywords) || "");

    watchEffect(() => {
        if (typeof document === "undefined") return;

        if (title.value) {
            document.title = title.value;
        }

        if (description.value) {
            const metaTag = getMetaTag("description");
            metaTag.setAttribute("content", description.value);
        }

        if (Object.prototype.hasOwnProperty.call(options, "keywords")) {
            const metaTag = getMetaTag("keywords");
            metaTag.setAttribute("content", keywords.value);
        }
    });
}
