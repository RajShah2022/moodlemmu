$(document).ready(function(){
    let ec = new EventCalendar(document.getElementById('ec'), {
        view: 'dayGridMonth',
        height: '800px',
        headerToolbar: {
            start: 'prev, today',
            center: 'title',
            end: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth, next'
        },
        scrollTime: '07:00:00',
        eventSources:[{
            url:M.cfg.wwwroot + '/blocks/eventtimetable/events.php',
            method: 'POST',
            extraParams:function () {
                return {
                    moduletype: $('#moduletype').val(),
                    moduleids: $('#moduleids').val(),
                    // instructorid: $('#instructorslist').val(),
                };
            }
        }],
        // buttonText: function (texts) {
        //     texts.resourceTimeGridWeek = 'resources';
        //     return texts;
        // },
        // resources: [
        //     {id: 1, title: 'Resource A'},
        //     {id: 2, title: 'Resource B'},
        //     {id: 3, title: 'Resource C'},
        //     {id: 4, title: 'Resource D'}
        // ],
        editable:false,
        dragScroll:false,
        disableDragging: true,
        eventDragMinDistance: 0,
        eventStartEditable: false,
        eventDurationEditable: false,
        eventClick:function (info) {
            return eventInfo(info);
        },
        eventDurationEditable:false,
        views: {
            timeGridWeek: {pointer: false},
            resourceTimeGridWeek: {pointer: true}
        },
        dayMaxEvents: true,
        nowIndicator: true,
        selectable: false,
    });
    function eventInfo(info) {
        console.log(info.event.extendedProps);
        require(['jquery', 'core/modal_factory', 'core/modal_events', 'core/fragment', 'core/templates'], function($, ModalFactory, ModalEvents, Fragment, Templates) {
            ModalFactory.create({
                    title: info.event.title,
                    type: ModalFactory.types.DEFAULT,
                    body: Templates.render('block_eventtimetable/eventview', info.event.extendedProps)
                }).done(function(modal) {
                    this.modal = modal;
                    modal.show();
                }.bind(this));
            // }.bind(this));
        });
    }
    $(document).on('change', '.eventcalendar_select_elem', function(){
        ec.refetchEvents();
    });
    $(document).on('change', '.form-control.w-100.eventcalendar_select_elem', function(){
        console.log($(this).data());
        var mastervalue = $(this).val();
        var action = $(this).data('action');
        var element = $(this).data('dependentelem');
        var selectvalue = $(this).data('dependentselectvalue');
        $.ajax({
            method: "POST",
            dataType: "json",
            url: M.cfg.wwwroot + "/blocks/eventtimetable/ajax.php",
            data: { action : action, mastervalue : mastervalue},
            success: function(data){
                var template = '<option value="">'+selectvalue+'</option>';
                $.each( data, function( index, value) {
                    template += '<option value = ' + index + ' >' +value + '</option>';
                });
                $("#"+element).html(template);
                $("#"+element).trigger('change');
            }
        });
    });
    $(document).ready(function(){
        // alert('hi');
        // $("#trainingunit").select2({
        //     placeholder: "Select a programming language",
        //     allowClear: true
        // });
    });
});
