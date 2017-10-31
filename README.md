# drupal-8-module-indicia_api

This version of the Indicia API module has been based upon [drupal-7-module-indicia_api](https://github.com/Indicia-Team/drupal-7-module-indicia-api).

The Indicia API module was created to allow external applications to communicate with the web-site
that hosts the module and so indirectly with the warehouse.


It replies with headers that enable cross-origin requests. The server hosting
the module must respond to http OPTIONS, GET, PUT and POST to support all the
available features.

For more information, see the [documentation](https://documenter.getpostman.com/view/1021123/indicia-api/6Yu2HT5).


## TODO

- [ ] Make sure correct CORS headers are being sent on all API requests
- [ ] Check user activation API methods work
- [ ] Confirm GET of reports works as expected, have checked with example in docs but not with full params
- [ ] Confirm all attributes of submitting a sample works as before
