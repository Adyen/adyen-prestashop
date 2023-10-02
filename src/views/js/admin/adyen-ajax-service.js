var Adyen = Adyen || {};

/**
 * Service for ajax HTTP communication.
 *
 * @returns {{post: post, get: get}}
 */
const adyenAjaxService = function() {
    /**
     * Sends an HTTP post request.
     *
     * @param url URL of the request
     * @param data Data to be sent
     * @param callback Callback to be called on response
     * @param format Request format
     * @param async Async flag
     */
    const post = (url, data, callback, format, async) => {
        let query = [];
        for (let key in data) {
            if (data.hasOwnProperty(key)) {
                query.push(encodeURIComponent(key) + '=' + encodeURIComponent(data[key]));
            }
        }

        send(url, callback, 'POST', query.join('&'), format, async);
    };

    /**
     * Sends an HTTP get request.
     *
     * @param url URL of the request
     * @param data Data to be sent
     * @param callback Callback to be called on response
     * @param format Request format
     * @param async Async flag
     */
    const get = (url, data, callback, format, async) => {
        let query = [];
        for (let key in data) {
            if (data.hasOwnProperty(key)) {
                query.push(encodeURIComponent(key) + '=' + encodeURIComponent(data[key]));
            }
        }

        send(
            url + (query.length ? '?' + query.join('&') : ''),
            callback,
            'GET',
            null,
            format,
            async
        );
    };

    /**
     * Returns latest supported HTTP request class.
     *
     * @returns {XMLHttpRequest|*} Latest supported HTTP Request class
     */
    const request = () => {
        let versions = [
            'MSXML2.XmlHttp.6.0',
            'MSXML2.XmlHttp.5.0',
            'MSXML2.XmlHttp.4.0',
            'MSXML2.XmlHttp.3.0',
            'MSXML2.XmlHttp.2.0',
            'Microsoft.XmlHttp'
        ];
        let xhr;
        let i;

        if (typeof XMLHttpRequest !== 'undefined') {
            return new XMLHttpRequest();
        }

        for (i = 0; i < versions.length; i++) {
            try {
                xhr = new ActiveXObject(versions[i]);
                break;
            } catch (e) {
            }
        }

        return xhr;
    };

    /**
     * Sends an HTTP request.
     *
     * @param url URL of the request
     * @param callback Callback to be called on response
     * @param method Method of the request
     * @param data Data to be sent
     * @param format Request format
     * @param async Async flag
     */
    const send = (url, callback, method, data, format, async) => {
        let x = request();

        if (async === undefined) {
            async = true;
        }

        x.open(method, url, async);
        x.onreadystatechange = function () {
            if (x.readyState === 4) {
                let response = x.responseText;
                let status = x.status;

                if (format === 'json') {
                    response = JSON.parse(response);
                }

                callback(response, status);
            }
        };

        if (method === 'POST') {
            x.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
        }

        x.send(data);
    };

    return {
        post: post,
        get: get
    }
}

let adyenAjaxServiceInstance = null;
Adyen.adyenAjaxService = () => {
    if (!adyenAjaxServiceInstance) {
        adyenAjaxServiceInstance = adyenAjaxService();
    }

    return adyenAjaxServiceInstance;
};
