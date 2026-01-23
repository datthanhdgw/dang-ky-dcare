const API = {
    /**
     * Fetch centers from API
     * @returns {Promise<Array>}
     */
    async fetchCenters() {
    const response = await fetch('api/centers.php');
        // console.log(response);
        const data = await response.json();
        if (data.status === 'error') throw new Error(data.message);
        return data.centers || [];
    },

    /**
     * Fetch customer types from API
     * @returns {Promise<Array>}
     */
    async fetchCustomerTypes() {
        const response = await fetch('api/customer-types.php');
        const data = await response.json();
        if (data.status === 'error') throw new Error(data.message);
        return data.types || [];
    },

    /**
     * Load PSC data by PSC number
     * @param {string} pscNo 
     * @returns {Promise<Object>}
     */
    async loadPSC(pscNo) {
        const response = await fetch(`api/load-psc.php?psc_no=${encodeURIComponent(pscNo)}`);
        const data = await response.json();
        if (data.status === 'error') throw new Error(data.message);
        return data;
    },

    /**
     * Save PSC data
     * @param {Object} masterData 
     * @param {Array} detailsData 
     * @returns {Promise<Object>}
     */
    async savePSC(masterData, detailsData) {
        const response = await fetch('api/save-psc.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                master: masterData,
                details: detailsData
            })
        });
        const data = await response.json();
        if (data.status === 'error') throw new Error(data.message);
        return data;
    },

    /**
     * Lookup MST (tax code) via VietQR API
     * @param {string} mst 
     * @returns {Promise<Object>}
     */
    async lookupMST(mst) {
        const response = await fetch(`https://api.vietqr.io/v2/business/${encodeURIComponent(mst)}`);
        const data = await response.json();
        return data;
    }
};

// Export for use in other modules
window.API = API;
