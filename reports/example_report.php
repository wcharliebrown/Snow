class ExampleReport {
    public function name() {
        return 'Example Report';
    }
    public function description() {
        return 'This is an example report';
    }
    public function sql_table() {
        return 'users';
    }
    public function sql_fields() {
        return 'id, name, email';
    }
    public function sql_where() {
        return 'status = "active"';
    }
    public function sql_order() {
        return 'created_date DESC';
    }
    public function rows_per_page() {
        return 20;
    }
    public function output_format() {
        return 'html';
    }
    public function html_header() {
        return '<h1>Hello, world!</h1>';
    }
    public function html_row_template() {
        return '<p>{{name}}</p>';
    }
    public function html_footer() {
        return '</body></html>';
    }
}