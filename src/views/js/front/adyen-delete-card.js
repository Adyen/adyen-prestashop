$(document).ready(function () {
    let buttons = $('.adyen-delete-btn');

    for (let i = 0; i < buttons.length; i++) {
        buttons[i].addEventListener('click', () => {
            const methodId = buttons[i].getAttribute('data-adyen-method-id');
            let modal = document.getElementById('adyen-modal-' + methodId);

            modal.style.display = "block";

            let cancelBtn = modal.getElementsByClassName('adyen-cancel-delete-btn-' + methodId)[0];

            cancelBtn.removeEventListener('click', removeModal);
            cancelBtn.addEventListener('click', removeModal);
            cancelBtn.setAttribute('methodId', methodId);

            let deleteBtn = modal.getElementsByClassName('adyen-confirm-delete-btn-' + methodId)[0];

            deleteBtn.removeEventListener('click', deleteMethod);
            deleteBtn.addEventListener('click', deleteMethod);
            deleteBtn.setAttribute('methodId', methodId);

            let closeModal = modal.getElementsByClassName('adyen-close-window-' + methodId)[0];

            closeModal.removeEventListener('click', removeModal);
            closeModal.addEventListener('click', removeModal);
            closeModal.setAttribute('methodId', methodId);
        })
    }

    function removeModal() {
        let methodId = this.getAttribute('methodId');
        let modal = document.getElementById('adyen-modal-' + methodId);

        modal.style.display = "none";
    }

    function deleteMethod() {
        let methodId = this.getAttribute('methodId');
        $.ajax({
            method: 'POST',
            dataType: 'json',
            url: ($('#adyen-delete-url')[0]).value,
            data: {
                cardId: methodId
            },
            success: function (response) {
                window.location.reload();
            },
            error: function () {
                console.log('error');
            }
        });
    }
})
