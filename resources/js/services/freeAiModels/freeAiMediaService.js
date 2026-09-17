import api from "@/services/ApiClient";
import modelCatalogService from "@/services/modelCatalog/modelCatalogService";

export const MEDIA_OPERATIONS = Object.freeze([
    "image_generation",
    "background_remove",
    "image_upscale",
    "image_edit",
    "remove_element",
    "restore",
    "outpaint",
    "resize",
    "video_generation",
]);

export const MEDIA_FILE_OPERATIONS = Object.freeze([
    "background_remove",
    "image_upscale",
    "image_edit",
    "remove_element",
    "restore",
    "outpaint",
    "resize",
]);

const fileOperations = new Set(MEDIA_FILE_OPERATIONS);
const isUpload = (value) => typeof Blob !== "undefined" && value instanceof Blob;

const assertOperation = (operation) => {
    const value = String(operation || "").trim();
    if (!MEDIA_OPERATIONS.includes(value)) {
        throw new TypeError(`Unsupported media operation: ${value}`);
    }
    return value;
};

export const mediaOperationRequiresFile = (operation) => fileOperations.has(assertOperation(operation));

const requiredNumber = (value, name) => {
    const parsed = Number(value);
    if (!Number.isInteger(parsed) || parsed <= 0) throw new TypeError(`${name} must be a positive integer`);
    return parsed;
};

const requiredText = (value, name) => {
    const parsed = String(value || "").trim();
    if (!parsed) throw new TypeError(`${name} is required`);
    return parsed;
};

const freeAiMediaService = {
    async getMediaModels(operation, options = {}) {
        return modelCatalogService.getModels("general_media", {
            ...options,
            force: true,
            operation: assertOperation(operation),
        });
    },

    async sendGeneralMedia({
        slug,
        conversationUuid,
        requestId,
        userId,
        modelId,
        selectedModelId,
        operation,
        parameters = {},
        userMessage = "",
        file = null,
    }) {
        const selectedOperation = assertOperation(operation);
        if (mediaOperationRequiresFile(selectedOperation) && !isUpload(file)) {
            throw new TypeError(`A file is required for ${selectedOperation}`);
        }
        if (!parameters || typeof parameters !== "object" || Array.isArray(parameters)) {
            throw new TypeError("parameters must be an object");
        }

        const uuid = requiredText(conversationUuid, "conversationUuid");
        const payload = {
            user_id: requiredNumber(userId, "userId"),
            model_id: requiredNumber(modelId, "modelId"),
            selected_model_id: requiredNumber(selectedModelId, "selectedModelId"),
            conversation_uuid: uuid,
            user_message: String(userMessage || "").trim(),
            state: {
                operation: selectedOperation,
                parameters: { ...parameters },
            },
            debug: true,
            request_id: requiredText(requestId, "requestId"),
        };
        const formData = new FormData();
        if (file) formData.append("file", file, file.name || "media-upload");
        formData.append("payload", JSON.stringify(payload));

        const response = await api.post(
            `/free-ai-models/${encodeURIComponent(requiredText(slug, "slug"))}/conversations/${encodeURIComponent(uuid)}/messages`,
            formData,
            {
                params: { catalog_operation: selectedOperation },
                timeout: 300000,
                suppressGlobalErrorToast: true,
            }
        );

        return response.data;
    },

    async downloadGeneratedFile(url) {
        const endpoint = requiredText(url, "url");
        if (!endpoint.startsWith("/api/v1/free-ai-model-files/")
            && !endpoint.startsWith("/free-ai-model-files/")) {
            throw new TypeError("Invalid generated media URL");
        }

        const response = await api.get(endpoint, {
            responseType: "blob",
            timeout: 180000,
            suppressGlobalErrorToast: true,
        });

        return response.data;
    },
};

export default freeAiMediaService;
