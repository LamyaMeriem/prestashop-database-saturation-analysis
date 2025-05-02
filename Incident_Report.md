# Incident Report : Unavailability of PrestaShop Back Office Access

**Incident date:** 02/05/2024
**Resolution date:** 02/05/2024
**System(s) affected:** Back Office PrestaShop (Administration interface)
**Service(s) affected:** Administrator login
**Impact:** Impossible for the administrator to connect to the Back Office. The Front Office (shop visible to customers) remained operational.
**Status:** Resolved

## 1 Executive summary

A customer reported being unable to connect to the Back Office of their PrestaShop shop on 02/05/2024, encountering a generic HTTP 500 error. 
Analysis revealed that the root cause was a saturation of the database connection tables (`ps_guest`, `ps_connections`, `ps_connections_source`) due to excessive and high frequency crawling by Microsoft's indexing bots (BingBot). 
The immediate solution was to empty these tables. Long-term mitigation measures were put in place, including limiting the crawl frequency via the `robots.txt` file and deploying an automatic database cleaning module (`dbcleaner`) via a cron task.

## 2. Chronology of Events

* 02/05/2024 09:15:** Customer report received: unable to connect to Back Office, error 500. Front Office working normally.
* 02/05/2024 09:30:** Activation of PrestaShop debug mode. No specific PHP error is displayed, only HTTP error 500.
* 02/05/2024 09:45:** Checked server logs (Hostinger): no suspicious entries or relevant errors identified. Examination of the database structure: the `ps_guest`, `ps_connections`, and `ps_connections_source` tables were found to be abnormally large.
* 02/05/2024 10:00:** Execute `TRUNCATE TABLE` command on `ps_guest`, `ps_connections`, and `ps_connections_source`.
* 02/05/2024 10:05:** Back Office connection test: successful. Administrator access restored.
* 02/05/2024 10:30:** Post-Incident Monitoring: Rapid recreation of entries in purged tables observed (approximately 7 to 14 entries per minute), indicating that the underlying cause had not been resolved.
* 02/05/2024 11:00:** Execution of a SQL query to identify the sources of frequent connections:
    ```sql
    SELECT INET_NTOA(ip_address) as ip_readable, COUNT(*) as nb_visits
    FROM ps_connections
    GROUP BY ip_readable
    ORDER BY nb_visits DESC
    LIMIT 20;
    ```
* 02/05/2024 11:15:** IP identification: The majority of IP addresses are in the ranges `157.55.x.x` and `207.46.x.x`. A `whois` search on these addresses confirms that they are official Microsoft crawlers (BingBot).
**02/05/2024 11:45 :** Mitigation `robots.txt` : Modification of the `robots.txt` file to slow down the BingBot crawl:
    ```
    User-agent: bingbot
    Crawl-delay: 60
    ```
**02/05/2024 12:15:** Validation Mitigation: Database monitoring: the rate at which entries are created in the connection tables is reduced to 2-3 per minute.
**02/05/2024 14:00 :** Additional Prevention: Development and deployment of the PrestaShop `dbcleaner` module configured with a cron task to periodically clean the connection tables.

## 3. Root Cause Analysis (RCA)

The incident was caused by BingBot indexing the site excessively quickly and frequently. This behaviour led to exponential growth in the PrestaShop tables dedicated to tracking visitor sessions and connections (`ps_guest`, `ps_connections`, `ps_connections_source`). 
The excessive size of these tables caused a degradation in database performance during requests linked to the Back Office connection process, resulting in an internal error masked by a generic HTTP 500 response.

## 4. Resolution and Recovery

* Immediate action:** The dump (`TRUNCATE`) of the `ps_guest`, `ps_connections`, and `ps_connections_source` tables immediately restored access to the Back Office.

## 5. Mitigation and prevention measures

* Crawl limitation:** The addition of the `Crawl-delay: 60` directive for `bingbot` in the `robots.txt` file significantly reduced the load induced by this specific crawler.
* Automated cleaning:** The `dbcleaner` module has been developed and implemented. An associated cron job periodically executes a function to clean up the connection tables, thus preventing them from growing excessively in the future.

## 6. Recommendations

* Continue to monitor the size of connection tables and server resources (CPU, RAM, disk I/O).
* Periodically check the effectiveness of the `Crawl-delay` directive in `robots.txt`.
* Ensure that the cron job associated with the `dbcleaner` module is running correctly and reliably.
