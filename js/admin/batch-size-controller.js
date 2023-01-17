document.addEventListener("DOMContentLoaded", function () {
    const bulkUploadField = document.querySelector("#useBulkUpload");
    const batchSizeWrapper = document.querySelector("#batchSize__wrapper");
    const batchSizeField = document.querySelector("#batchSize");

    // Handle toggling display of our batch size field
    if (bulkUploadField && batchSizeWrapper) {
        bulkUploadField.addEventListener("input", function () {
            batchSizeWrapper.classList.toggle("hidden");
        });
    }

    // Some simple validation
    if (batchSizeField) {
        batchSizeField.addEventListener("input", function (event) {
            let batchSize = parseInt(batchSizeField.value);
            let minBatchSize = parseInt(batchSizeField.getAttribute("min"));
            let maxBatchSize = parseInt(batchSizeField.getAttribute("max"));
            if (batchSize < minBatchSize) {
                batchSize = minBatchSize;
            } else if (batchSize > maxBatchSize) {
                batchSize = maxBatchSize;
            }
            batchSizeField.value = batchSize;
        });
    }
});
