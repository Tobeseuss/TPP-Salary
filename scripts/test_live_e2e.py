# -*- coding: utf-8 -*-
"""End-to-end live test of the SHIPPED app code path (build tree) vs real site.
Read-only: GET ping + GET bundle. Key via env TPP_TEST_KEY."""
import os
import sys

BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.join(BASE, "build", "tpp_salary", "python-app"))

from app.api_client import ApiClient  # noqa: E402


class Cfg(object):
    def get(self, key, fallback=None):
        vals = {
            "api_url": "https://tpptc.ir/wp-json/tpp_salary/v1",
            "api_key": os.environ.get("TPP_TEST_KEY", ""),
        }
        return vals.get(key, fallback)


def main():
    c = ApiClient(Cfg())
    ok, msg, data = c.status_text()
    print("status_text:", "%s | %s" % ("ONLINE" if ok else "OFFLINE", msg))
    if not ok:
        return 1
    b = c.bundle()
    print("bundle: employees=%d centers=%d banks=%d records=%d version=%s"
          % (len(b.get("employees", [])), len(b.get("centers", [])),
             len(b.get("banks", [])), len(b.get("records", [])), b.get("version")))
    print("RESULT: ALL PASS")
    return 0


if __name__ == "__main__":
    sys.exit(main())
