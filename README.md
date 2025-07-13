# Dot 404

Handle requests with a dot for non-existent files.

## Run tests

```shell
php -S 127.0.0.1:8080 tests/router.php

# On another terminal
curl -v http://127.0.0.1:8080/
curl -v http://127.0.0.1:8080/wp-sitemap-posts-post-1.xml
curl -v http://127.0.0.1:8080/wp-sitemap.xsl
curl -v http://127.0.0.1:8080/ajax-json.dot -H 'X-Requested-With: xmlhttprequest' -H 'Accept: application/json'
curl -v http://127.0.0.1:8080/ajax-json.dot -H 'X-Requested-With: xmlhttprequest' -H 'Accept: application/xml'
curl -v http://127.0.0.1:8080/ajax-json.dot -H 'X-Requested-With: xmlhttprequest' -H 'Accept: text/html, text/plain;q=0.9'
curl -v http://127.0.0.1:8080/ajax-json.dot -H 'X-Requested-With: xmlhttprequest' -H 'Accept: text/html'
curl -v http://127.0.0.1:8080/something.wild
curl -v http://127.0.0.1:8080/give-me-a.jpg | hexdump -C
curl -v http://127.0.0.1:8080/give-me-a.PNG | hexdump -C
```
