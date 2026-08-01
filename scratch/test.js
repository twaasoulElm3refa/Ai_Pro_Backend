import { normalizeGeneratedImages, normalizeImageGenerationMessage } from "../resources/js/utils/imageGeneratorResults.js";

const result = {
  "success": true,
  "message": "Background removed successfully.",
  "files": [
    {
      "file_id": "...",
      "filename": "LEGO_logo.svg-no-background.png",
      "content_type": "image/png",
      "download_url": "/tasks/generated-files/download/..."
    }
  ]
};

const rawImages = result.images || result.files || [];
const images = normalizeGeneratedImages(rawImages);
const assistant = normalizeImageGenerationMessage({
    id: result.assistant_message_id,
    role: "assistant",
    content: result.message,
    is_error: result.success === false || result.type === "error",
    images,
    state: result.state,
    metadata: {
        ...result,
        images,
        generation: result.metadata,
        request_prompt: "test",
        failed_files: result.failed_files || [],
    },
}, 0);

console.log(JSON.stringify(assistant, null, 2));
