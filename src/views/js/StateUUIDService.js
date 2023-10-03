if (!window.AdyenFE) {
    window.AdyenFE = {};
}

(() => {
    function StateUUIDService() {
        let currentState = '';

        this.setStateUUID = () => {
            currentState = Math.random().toString(36);
        };

        this.getStateUUID = () => {
            return currentState;
        };
    }

    AdyenFE.StateUUIDService = new StateUUIDService();
})();
