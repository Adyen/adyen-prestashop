;var AdyenComponents = window.AdyenComponents || {};

(function () {
    'use strict';

    /**
     * @constructor
     *
     * @param {{
     *     donationsConfigUrl : string,
     *     makeDonation: function
     * }} config
     */
    function AdyenDonationsController(config) {
        let donations,
            activeComponent,
            isStateValid = true;

        const getDonationsInstance = async () => {
            if (!donations) {
                let donationsConfig = await (await fetch(config.donationsConfigUrl, {
                    method: "GET"
                })).json().catch((error) => {
                    return null
                });

                if (donationsConfig.length === 0) {
                    return null;
                }

                if (donationsConfig.errorCode) {
                    throw 'Donations configuration error';
                }

                donations = await AdyenCheckout(donationsConfig);
            }

            return Promise.resolve(donations);
        }

        const handleOnDonate = (state, component) => {
            isStateValid = state.isValid;
            if (isStateValid) {
                config.makeDonation(state.data);
            }
        };

        const handleOnCancel = (state, component) => {
            unmount();
        }

        const mount = (mountingElement) => {
            let me = this,
                donationInstance = getDonationsInstance();
            isStateValid = true;

            donationInstance.then((donationInstance) => {
                if (!donationInstance) {
                    return;
                }

                unmount();

                activeComponent = donationInstance.create('donation', {
                    'onDonate': handleOnDonate,
                    'onCancel': handleOnCancel
                })
                    .mount(mountingElement);
            })
        }

        const unmount = () => {
            isStateValid = true;

            if (activeComponent && donations) {
                donations.remove(activeComponent);
                activeComponent = null;
            }
        }

        this.mount = mount;
        this.unmount = unmount;
    }

    AdyenComponents.DonationsController = AdyenDonationsController;
})();
