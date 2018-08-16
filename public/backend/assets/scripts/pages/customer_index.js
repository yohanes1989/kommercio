var CustomerIndex = function () {
  $('#customer-filter-btn').on('click', function(e){
      e.preventDefault();

      var queryString = $(':input', '#customer-filter-form').map(function () {
          return $(this).val().trim() == "" ? null : this;
      }).serialize();
      window.location.href = $('#customer-filter-form').data('customer_index') + '?external_filter=1&' + queryString;
  });

    var initTable = function () {
      var $dataTable = new Datatable();

      var runtimeAdditonalColumns = 0;
      var columnDefs = [
        {"name": "no", "targets": 0, "orderable": false},
        {"name": "salute", "targets": 1, "orderable": false},
        {"name": "full_name", "targets": 2},
        {"name": "email", "targets": 3},
        {"name": "account", "targets": 4, "orderable": false},
        {"name": "status", "targets": 5, "orderable": false}
      ]

      if(enable_customer_group){
        columnDefs.push({"name": "customer_group", "orderable": false, "targets": 6});
        runtimeAdditonalColumns += 1;
      }

      columnDefs = columnDefs.concat([
        {"name": "created_at", "targets": 6+runtimeAdditonalColumns},
        {"name": "last_active", "targets": 7+runtimeAdditonalColumns},
        {"name": "total_orders", "targets": 8+runtimeAdditonalColumns},
        {"name": "total", "targets": 9+runtimeAdditonalColumns},
        {"name": "action", "orderable" : false, "targets": 10+runtimeAdditonalColumns}
      ]);

        $dataTable.init({
            token: $('#customers-dataset').data('form_token'),
            src: $('#customers-dataset'),
            filterApplyAction: $('#customers-dataset').data('src'),
            filterCancelAction: $('#customers-dataset').data('src'),
            onSuccess: function (grid, response) {
                // grid:        grid object
                // response:    json object of server side ajax response
                // execute some code after table records loaded
            },
            onError: function (grid) {
                // execute some code on network or other general error
            },
            onDataLoad: function(grid) {
                // execute some code on ajax data load
                App.initComponents();
            },
            loadingMessage: 'Loading...',
            dataTable: { // here you can define a typical datatable settings from http://datatables.net/usage/options

                // Uncomment below line("dom" parameter) to fix the dropdown overflow issue in the datatable cells. The default datatable layout
                // setup uses scrollable div(table-scrollable) with overflow:auto to enable vertical scroll(see: assets/global/scripts/datatable.js).
                // So when dropdowns used the scrollable div should be removed.
                //"dom": "<'row'<'col-md-8 col-sm-12'pli><'col-md-4 col-sm-12'<'table-group-actions pull-right'>>r>t<'row'<'col-md-8 col-sm-12'pli><'col-md-4 col-sm-12'>>",

                "bStateSave": true,

                "lengthMenu": [
                    [50, 100, 150, 200, -1],
                    [50, 100, 150, 200, "All"] // change per page values here
                ],
                "pageLength": 50, // default record count per page
                "ajax": {
                    "url": $('#customers-dataset').data('src')
                },
                "searching": false,
                "processing": true,
                "serverSide": true,
                "order": [
                    [6+runtimeAdditonalColumns, "desc"]
                ],
                "columnDefs": columnDefs
            }
        });
    }

    return {

        //main function to initiate the module
        init: function () {

            if (!jQuery().dataTable) {
                return;
            }

            initTable();
        }

    };

}();

jQuery(document).ready(function() {
    CustomerIndex.init();
});
