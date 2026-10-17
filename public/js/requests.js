function showNotify(title, message, type = 'success') {
    new Notify({
        status: type,
        title: title,
        text: message,
        effect: 'fade',
        speed: 300,
        showIcon: true,
        showCloseButton: true,
        autoclose: true,
        type: 1,
        position: 'right top'
    })
}

$(document).on('submit', '.axios-form', function (e) {
    // alert("AMIGO ESTOU AQUI");
    e.preventDefault();
    blockpage();
    try {
        let url = $(this).attr('action');
        let data = new FormData(this)
        if (!url)
            throw "URL não encontrada";


        axios.post(url, data)
            .then(res => {
                unblockpage();
                if (!res.data.success)
                    throw "Ocorreu um erro ao processar sua requisição";

                if ($('#dataTableBuilder').length) {
                    $('#dataTableBuilder').DataTable().ajax.reload();
                }
                showNotify('Sucesso', res.data.message || "Seus dados foram atualizados com sucesso");

                $('.modal').modal('hide');
            }).catch((err, data) => {
            console.log('data', data)
            unblockpage();
            let message = err.response?.data?.message || "Ocorreu um erro ao tentar enviar os dados"; //typeof err.response?.data?.message === 'string' || err.response?.data?.message instanceof String ? err : (err.message && (typeof err.message === 'string' || err.message instanceof String) ? err.message : "Ocorreu um erro ao tentar enviar os dados")
            showNotify('Atenção', message || 'Ocorreu um erro, informa ao administrador', 'error');

        })

    } catch (e) {
        unblockpage();
        console.error('error', e);
        showNotify('Atenção', e.message || 'Ocorreu um erro, informa ao administrador', 'error');
    }
});


function blockpage() {
//TODO: criar bloqueio de tela
}

function unblockpage() {
//TODO: criar desbloqueio de tela
}




function requestIntoModal(url) {
//TODO: ativar select2

    blockpage();
    axios.get(url).then(view => {
        unblockpage();

        $('#modal-default').html(view.data);
        $('#modal-default').modal('show');

        //
        // $('.searchableselect').select2({
        //     closeOnSelect: true,
        // });
    }).catch((erro) => {
        unblockpage();
        console.error('ERRO', erro)
        showNotify('Atenção', 'Ocorreu um erro ao carregar as informações', 'error');
    })

}


async function deleteDefault(url, data = {}, msg_alert = 'Deseja realmente excluir?', closeModal = true) {

    if (confirm(msg_alert)) {
        data._method = 'DELETE';
        return axios.post(url, data).then(res => {
            unblockpage();
            if (!res.data.success)
                throw "Ocorreu um erro ao processar sua requisição";

            if ($('#dataTableBuilder').length) {
                $('#dataTableBuilder').DataTable().ajax.reload();
            }

            showNotify('Sucesso', res.data.message || "Seus dados foram excluidos com sucesso");

            if (closeModal)
                $('.modal').modal('hide');
            return true;
        }).catch((err, data) => {
            unblockpage();
            let message = err.response?.data?.message || "Ocorreu um erro ao tentar enviar os dados"; //typeof err.response?.data?.message === 'string' || err.response?.data?.message instanceof String ? err : (err.message && (typeof err.message === 'string' || err.message instanceof String) ? err.message : "Ocorreu um erro ao tentar enviar os dados")
            showNotify('Atenção', message, 'error');
            return false;
        })
    }
    return false;
}


/**
 * POST simples a uma ação que responde {success, message}: pergunta antes (se
 * houver pergunta), recarrega a tabela da tela e mostra a mensagem.
 */
async function postAction(url, pergunta = null, data = {}) {
    if (pergunta && !confirm(pergunta)) {
        return false;
    }

    blockpage();
    return axios.post(url, data).then(res => {
        unblockpage();
        if ($('#dataTableBuilder').length) {
            $('#dataTableBuilder').DataTable().ajax.reload(null, false);
        }
        showNotify('Sucesso', res.data.message || 'Pedido enviado.');
        return true;
    }).catch(err => {
        unblockpage();
        showNotify('Atenção', err.response?.data?.message || 'Ocorreu um erro ao enviar o pedido.', 'error');
        return false;
    });
}
