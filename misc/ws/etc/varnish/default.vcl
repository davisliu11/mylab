# This is a basic VCL configuration file for varnish.  See the vcl(7)
# man page for details on VCL syntax and semantics.
# 
# Default backend definition.  Set this to point to your content
# server.
# 
backend default {
    .host = "127.0.0.1";
    .port = "8085";
}


acl purge {
        "localhost";
}

sub vcl_recv {
        /* Before anything else we need to fix gzip compression */
        if (req.http.Accept-Encoding) {
                if (req.url ~ "\.(jpg|png|gif|gz|tgz|bz2|tbz|mp3|ogg)$")
                {
                        # No point in compressing these
                        remove req.http.Accept-Encoding;
                } else if (req.http.Accept-Encoding ~ "gzip") {
                        set req.http.Accept-Encoding = "gzip";
                } else if (req.http.Accept-Encoding ~ "deflate") {
                        set req.http.Accept-Encoding = "deflate";
                } else {
                        # unknown algorithm
                        remove req.http.Accept-Encoding;
                }
        }

        if (req.restarts == 0) {
                if (req.http.x-forwarded-for) {
                        set req.http.X-Forwarded-For =
                        req.http.X-Forwarded-For ", " client.ip;
                } else {
                        set req.http.X-Forwarded-For = client.ip;
                }
        }

        if (req.request != "GET" &&
          req.request != "HEAD" &&
          req.request != "PUT" &&
          req.request != "POST" &&
          req.request != "TRACE" &&
          req.request != "OPTIONS" &&
          req.request != "DELETE") {
                /* Non-RFC2616 or CONNECT which is weird. */
                return (pipe);
        }

        if (req.request == "PURGE") {
                if (!client.ip ~ purge) {
                        error 405 "Not allowed.";
                }
                /* Always purge by URL rather than going via vcl_hash
                   as it hashes other factors which break purging */
                purge_url(req.url);
                error 200 "Purged";
        }


        if (req.request != "GET" && req.request != "HEAD") {
                /* We only deal with GET and HEAD by default */
                return (pass);
        }

        if (req.http.Authorization || req.http.If-None-Match) {
                /* Not cacheable by default */
                return (pass);
        }

        if (req.url ~ "\.php") {
                return (pass);
        }

	if (req.http.host ~ "(www.)?(foo.co.nz)$") {
                /* Do not cache specific hosts */
		return(pass);
	}

        if (req.url ~ "\.(xml|png|gif|jpg|bmp|ico|flv|swf|css|js)$") {
                remove req.http.Cookie;
                return (lookup);
        }

        if (req.backend.healthy) {
                set req.grace = 90s;
        } else {
                set req.grace = 2h;
        }

        return (lookup);
}

sub vcl_fetch {
        set beresp.grace = 2h;

        # Varnish determined the object was not cacheable
        if (!beresp.cacheable) {
                set beresp.http.X-Cacheable = "NO:Not Cacheable";
        # You don't wish to cache content for logged in users
        } elsif (req.http.Cookie || beresp.http.Set-Cookie) {
                set beresp.http.X-Cacheable = "NO:Got Cookie";
                return(pass);
        # You are respecting the Cache-Control=private header from the backend
        } elsif (beresp.http.Cache-Control ~ "(private|no-cache|no-store)") {
                set beresp.http.X-Cacheable = "NO:Cache-Control";
                return(pass);
        # You are excluding something from being cached
        } elsif (req.url ~ "\.php") {
                set beresp.http.X-Cacheable = "NO:FORCED:Specific";
		return(pass);
        # You are extending the lifetime of the object artificially
        } elsif (req.url ~ "\.(xml|png|gif|jpg|bmp|ico|flv|swf|css|js)$") {
                unset beresp.http.set-cookie;
                set beresp.ttl = 300s;
                set beresp.http.X-Cacheable = "YES:FORCED:Specific";
        # Varnish determined the object was cacheable
        } else {
                set beresp.http.X-Cacheable = "YES";
        }

        return(deliver);
}

sub vcl_deliver {
  set resp.http.X-Served-By = server.hostname;
  if (obj.hits > 0) {
    set resp.http.X-Cache = "HIT";	
    set resp.http.X-Cache-Hits = obj.hits;
  } else {
    set resp.http.X-Cache = "MISS";	
  }

  return (deliver);
}


# Below is a commented-out copy of the default VCL logic.  If you
# redefine any of these subroutines, the built-in logic will be
# appended to your code.
# 
# sub vcl_recv {
#     if (req.restarts == 0) {
# 	if (req.http.x-forwarded-for) {
# 	    set req.http.X-Forwarded-For =
# 		req.http.X-Forwarded-For ", " client.ip;
# 	} else {
# 	    set req.http.X-Forwarded-For = client.ip;
# 	}
#     }
#     if (req.request != "GET" &&
#       req.request != "HEAD" &&
#       req.request != "PUT" &&
#       req.request != "POST" &&
#       req.request != "TRACE" &&
#       req.request != "OPTIONS" &&
#       req.request != "DELETE") {
#         /* Non-RFC2616 or CONNECT which is weird. */
#         return (pipe);
#     }
#     if (req.request != "GET" && req.request != "HEAD") {
#         /* We only deal with GET and HEAD by default */
#         return (pass);
#     }
#     if (req.http.Authorization || req.http.Cookie) {
#         /* Not cacheable by default */
#         return (pass);
#     }
#     return (lookup);
# }
# 
# sub vcl_pipe {
#     # Note that only the first request to the backend will have
#     # X-Forwarded-For set.  If you use X-Forwarded-For and want to
#     # have it set for all requests, make sure to have:
#     # set bereq.http.connection = "close";
#     # here.  It is not set by default as it might break some broken web
#     # applications, like IIS with NTLM authentication.
#     return (pipe);
# }
# 
# sub vcl_pass {
#     return (pass);
# }
# 
# sub vcl_hash {
#     set req.hash += req.url;
#     if (req.http.host) {
#         set req.hash += req.http.host;
#     } else {
#         set req.hash += server.ip;
#     }
#     return (hash);
# }
# 
# sub vcl_hit {
#     if (!obj.cacheable) {
#         return (pass);
#     }
#     return (deliver);
# }
# 
# sub vcl_miss {
#     return (fetch);
# }
# 
# sub vcl_fetch {
#     if (!beresp.cacheable) {
#         return (pass);
#     }
#     if (beresp.http.Set-Cookie) {
#         return (pass);
#     }
#     return (deliver);
# }
# 
# sub vcl_deliver {
#     return (deliver);
# }
# 
# sub vcl_error {
#     set obj.http.Content-Type = "text/html; charset=utf-8";
#     synthetic {"
# <?xml version="1.0" encoding="utf-8"?>
# <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
#  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
# <html>
#   <head>
#     <title>"} obj.status " " obj.response {"</title>
#   </head>
#   <body>
#     <h1>Error "} obj.status " " obj.response {"</h1>
#     <p>"} obj.response {"</p>
#     <h3>Guru Meditation:</h3>
#     <p>XID: "} req.xid {"</p>
#     <hr>
#     <p>Varnish cache server</p>
#   </body>
# </html>
# "};
#     return (deliver);
# }
