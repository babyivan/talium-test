var websocket;

var last_sector_selected;
var last_place_selected;

var user_name;

function showMessage(messageHTML) {
    $('#log').append(messageHTML);
}

function unblock_ui() {
}

function block_ui() {
    add_display_none('#root-sectors');
    add_display_none('#root-places');
    add_display_none('#root-places_info');
}

function add_display_none(_id) {
    var i = $(_id);
    i.addClass("display-none");
}

function remove_display_none(_id) {
    var i = $(_id);
    i.removeClass("display-none");
}

function action_click(el, event) {
    event.preventDefault();
    var param;

    if (el.name === "account-login") {
        param = alert_val("Введите ваш ID");

        ws_send({
            action: "login",
            action_data: param
        });
    }
    else if (el.name === "account-register") {
        param = alert_val("Придумайте ID (например: :124FS");

        ws_send({
            action: "register",
            action_data: param
        });
    }
    else if (el.name === "place_reserve") {
        ws_send({
            action: "place_reserve"
        });
    }
    else if (el.name === "place_buy") {
        ws_send({
            action: "place_buy"
        });
    }
}

function alert_val(text) {
    var userText = "";

    while (userText.length < 1) {
        userText = prompt(text);
    }
    return userText;
}

function ws_send(data) {
    websocket.send(JSON.stringify(data));
}

function sector_select(e, sector) {
    if (last_sector_selected !== undefined)
        $(last_sector_selected).removeClass('selected');

    last_sector_selected = e;
    $(last_sector_selected).addClass('selected');

    ws_send({
        action: "selected_sector",
        action_data: sector
    });
}

function place_select(e, place) {
    if (last_place_selected !== undefined)
        $(last_place_selected).removeClass('selected');

    last_place_selected = e;
    $(last_place_selected).addClass('selected');
    ws_send({
        action: "selected_place",
        action_data: place
    });
}

window.onload = function () {
    block_ui();

    if (!("WebSocket" in window)) {
        $('<p>Oh nooooooooooo, you need a browser that supports WebSockets. How about <a href="http://www.google.com/chrome">Google Chrome</a>?</p>').appendTo('#container');
    }
    else {
        websocket = new WebSocket("ws://t1r.localhost.com/ws");
        websocket.onopen = function (event) {
            unblock_ui();
            showMessage("<div class='chat-connection'>Connection is established!</div>");
        };
        websocket.onmessage = function (event) {
            console.log(event.data);

            var server_response = JSON.parse(event.data);

            if (server_response.type === 'msg') {
                showMessage("<div>" + server_response.data + "</div>");
            }

            else if (server_response.type === 'register') {
                if (server_response.data.status === true)
                    user_name = server_response.data.user_name;
                else
                    alert(server_response.data.msg);
            }

            else if (server_response.type === 'login') {
                showMessage("<div>" + server_response.data.msg + "</div>");

                ws_send({
                    action: "sectors_get"
                });
            }

            else if (server_response.type === 'sectors') {
                var sectors = $('<ul></ul>');

                jQuery.each(server_response.data, function (i, val) {
                    var row = $('<li></li>').addClass('float-left').append($('<a>', {
                            title: 'Сектор - ' + i + '  [Всего мест: ' + val.all_places + '; Забронировано: ' + val.reserved_places + '; Куплено: ' + val.purchased_places + ']',
                            href: '#',
                            onclick: 'sector_select(this, ' + i + ');',
                            id: 'sector-' + i
                        }
                    ).html(i + ' <small class="subText">[' + val.all_places + '/' + val.reserved_places + '/' + val.purchased_places + ']</small>'));

                    sectors.append(row);
                });

                remove_display_none('#root-sectors');
                $('#sectors').html(sectors);
            }

            else if (server_response.type === 'sector_update') {
                var s = $('#sector-' + server_response.data.sector);
                s.attr({
                    title: 'Сектор - ' + server_response.data.sector + '  [Всего мест: ' + server_response.data.data.all_places + '; Забронировано: ' + server_response.data.data.reserved_places + '; Куплено: ' + server_response.data.data.purchased_places + ']',
                    href: '#',
                    onclick: 'sector_select(this, ' + server_response.data.sector + ');'
                }).html(server_response.data.sector + ' <small class="subText">[' + server_response.data.data.all_places + '/' + server_response.data.data.reserved_places + '/' + server_response.data.data.purchased_places + ']</small>');
            }

            else if (server_response.type === 'places') {
                add_display_none('#root-places_info');
                var places = $('<ul></ul>');

                jQuery.each(server_response.data, function (i, val) {

                    var status = "";
                    if (val.status === 1)
                        status = "reserved";
                    else if (val.status === 2)
                        status = "purchased";


                    var row = $('<li></li>').addClass('float-left').append($('<a>', {
                            text: i,
                            title: 'Место - ' + i,
                            href: '#',
                            onclick: 'place_select(this, ' + i + ');',
                            class: status
                        }
                    ));
                    places.append(row);
                });

                remove_display_none('#root-places');
                $('#places').html(places);
            }

            else if (server_response.type === 'place_info') {
                $('#place_title').html('Информация о [Сектор: ' + last_sector_selected.innerHTML + ', Место: ' + last_place_selected.innerHTML + '] :');
                var place_info = $('<div></div>');

                var status = $('<p></p>').text("Статус: " + server_response.data.status);
                place_info.append(status);

                var owner = $('<p></p>').text("Владелец: " + server_response.data.owned);
                place_info.append(owner);

                var form_action = $("<form/>",
                    {action: '#'}
                );
                form_action.append(
                    $("<input>",
                        {
                            type: 'submit',
                            name: 'place_reserve',
                            value: 'Зарезервировать',
                            onclick: 'action_click(this, event);',
                            disabled: (server_response.data.owned !== user_name && server_response.data.status !== 0)
                        }
                    )
                );

                form_action.append(
                    $("<input>",
                        {
                            type: 'submit',
                            name: 'place_buy',
                            value: 'Купить',
                            onclick: 'action_click(this, event);',
                            disabled: (server_response.data.owned !== user_name && server_response.data.status !== 0)
                        }
                    )
                );

                place_info.append(form_action);

                remove_display_none('#root-places_info');
                $('#places_info').html(place_info);
            }
            return false;
        };

        websocket.onerror = function (event) {
            showMessage("<div class='error'>Problem due to some Error</div>");
        };
        websocket.onclose = function (event) {
            showMessage("<div>Connection Closed</div>");
        };
    }
};