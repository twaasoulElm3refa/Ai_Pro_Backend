import api from "@/services/ApiClient";
import walletService from "@/services/profile/walletService";

const unwrap = (response) => response.data;
const operationParams = (catalogOperation) => catalogOperation
    ? { params: { catalog_operation: catalogOperation } }
    : undefined;

const freeAiModelService = {
    async getModels() {
        return unwrap(await api.get("/free-ai-models"));
    },

    async getModel(slug) {
        return unwrap(await api.get(`/free-ai-models/${slug}`));
    },

    async getConversations(slug, catalogOperation = null) {
        return unwrap(await api.get(
            `/free-ai-models/${slug}/conversations`,
            operationParams(catalogOperation)
        ));
    },

    async createConversation(slug, selectedModel = null, catalogOperation = null) {
        const payload = selectedModel
            ? {
                  catalog_model_id: selectedModel.id,
                  provider_model_id: selectedModel.providerModelId || null,
              }
            : {};

        if (catalogOperation) payload.catalog_operation = catalogOperation;

        return unwrap(await api.post(`/free-ai-models/${slug}/conversations`, payload));
    },

    async getConversation(slug, uuid, catalogOperation = null) {
        return unwrap(await api.get(
            `/free-ai-models/${slug}/conversations/${uuid}`,
            operationParams(catalogOperation)
        ));
    },

    async getMessages(slug, uuid, cursor = null, catalogOperation = null) {
        return unwrap(await api.get(`/free-ai-models/${slug}/conversations/${uuid}/messages`, {
            params: {
                ...(cursor ? { cursor } : {}),
                ...(catalogOperation ? { catalog_operation: catalogOperation } : {}),
            },
        }));
    },

    async sendMessage(slug, uuid, userMessage, requestId, catalogOperation = null) {
        return unwrap(await api.post(`/free-ai-models/${slug}/conversations/${uuid}/messages`, {
            user_message: userMessage,
            request_id: requestId,
        }, {
            ...operationParams(catalogOperation),
            timeout: 120000,
            suppressGlobalErrorToast: true,
        }));
    },

    async sendGeneralCodeMessage(slug, uuid, userMessage, requestId, programmingLanguage) {
        return unwrap(await api.post(`/free-ai-models/${slug}/conversations/${uuid}/messages`, {
            user_message: userMessage,
            request_id: requestId,
            programming_language: programmingLanguage,
        }, {
            timeout: 120000,
            suppressGlobalErrorToast: true,
        }));
    },

    async sendGeneralTranslationMessage(slug, uuid, userMessage, requestId, sourceLanguage, targetLanguage) {
        return unwrap(await api.post(`/free-ai-models/${slug}/conversations/${uuid}/messages`, {
            user_message: userMessage,
            request_id: requestId,
            source_language: sourceLanguage,
            target_language: targetLanguage,
        }, {
            timeout: 120000,
            suppressGlobalErrorToast: true,
        }));
    },

    async sendSpeechToTextMessage(slug, uuid, audioFile, requestId, context) {
        const payload = {
            user_id: Number(context.userId),
            model_id: Number(context.modelId),
            selected_model_id: Number(context.selectedModelId),
            conversation_uuid: uuid,
            user_message: "حوّل الصوت إلى نص",
            state: {
                operation: "speech_to_text",
                parameters: {
                    language: "ar",
                    include_segments: true,
                },
            },
            debug: true,
            request_id: requestId,
        };
        const formData = new FormData();
        formData.append("file", audioFile);
        formData.append("payload", JSON.stringify(payload));

        return unwrap(await api.post(
            `/free-ai-models/${slug}/conversations/${uuid}/messages`,
            formData,
            {
                ...operationParams("speech_to_text"),
                timeout: 300000,
                suppressGlobalErrorToast: true,
            }
        ));
    },

    // The send endpoint saves both messages and charges the wallet atomically.
    async saveMessage(slug, uuid, userMessage, requestId, catalogOperation = null) {
        return this.sendMessage(slug, uuid, userMessage, requestId, catalogOperation);
    },

    async getWallet() {
        return walletService.getWallet();
    },

    async updateConversationModel(slug, uuid, selectedModel, catalogOperation = null) {
        return unwrap(await api.patch(`/free-ai-models/${slug}/conversations/${uuid}/model`, {
            catalog_model_id: selectedModel.id,
            provider_model_id: selectedModel.providerModelId || null,
            ...(catalogOperation ? { catalog_operation: catalogOperation } : {}),
        }, operationParams(catalogOperation)));
    },

    async deleteConversation(slug, uuid, catalogOperation = null) {
        return unwrap(await api.delete(
            `/free-ai-models/${slug}/conversations/${uuid}`,
            operationParams(catalogOperation)
        ));
    },
};

export default freeAiModelService;
