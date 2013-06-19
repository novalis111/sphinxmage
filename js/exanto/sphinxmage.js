Varien.searchForm.prototype._selectAutocompleteItem = function (element) {
    if (element.title) {
        this.field.value = element.title;
        this.form.submit();
    }
}