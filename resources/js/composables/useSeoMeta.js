import { computed, unref, watchEffect } from "vue";

const getMetaDescriptionTag = () => {
    let tag = document.querySelector('meta[name="description"]');

    if (!tag) {
        tag = document.createElement("meta");
        tag.setAttribute("name", "description");
        document.head.appendChild(tag);
    }

    return tag;
};

export default function useSeoMeta(options = {}) {
    const title = computed(() => unref(options.title) || "");
    const description = computed(() => unref(options.description) || "");

    watchEffect(() => {
        if (typeof document === "undefined") return;

        if (title.value) {
            document.title = title.value;
        }

        if (description.value) {
            const metaTag = getMetaDescriptionTag();
            metaTag.setAttribute("content", description.value);
        }
    });
}
