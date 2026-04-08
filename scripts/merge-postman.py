#!/usr/bin/env python3
import argparse
import json
import re
from pathlib import Path
from typing import Any, Dict, List, Optional, Tuple
from urllib.parse import urlsplit


class Node:
    def __init__(self, token: str) -> None:
        self.token = token
        self.name: Optional[str] = None
        self.fallback_name: Optional[str] = None
        self.order: Optional[int] = None
        self.requests_order: Optional[int] = None
        self.children: Dict[str, "Node"] = {}
        self.requests: List[Tuple[int, str, int, dict]] = []


DEFAULT_ORDER = 10_000
LEGACY_PRIORITY = 1
TREE_INDEX_PRIORITY = 2
TREE_ENDPOINT_PRIORITY = 3
HTTP_METHODS = {"get", "post", "put", "patch", "delete", "options", "head", "ws"}


def parse_optional_int(value: Any) -> Optional[int]:
    if value is None:
        return None

    if isinstance(value, bool):
        return int(value)

    if isinstance(value, (int, float)):
        return int(value)

    text = str(value).strip()
    if text == "":
        return None

    return int(text)


def canonicalize_tokens(tokens: List[str]) -> List[str]:
    return [token.strip().lower() for token in tokens if token.strip()]


def display_name_from_token(token: str) -> str:
    text = token.strip()
    if text == "":
        return text

    if text.isupper() and len(text) <= 5:
        return text

    text = text.replace("-", " ").replace("_", " ")
    text = re.sub(r"\s+", " ", text).strip()
    return text.title()


def endpoint_sort_key(file_stem: str) -> str:
    return file_stem.lower()


def endpoint_identity_key(item: dict) -> Optional[str]:
    request = item.get("request")
    if not isinstance(request, dict):
        return None

    method = str(request.get("method", "")).strip().upper()
    if method == "":
        return None

    tokens = url_path_tokens(request.get("url"))
    if not tokens:
        return None

    normalized_path = "/" + "/".join(token.lower() for token in tokens)
    return f"{method} {normalized_path}"


def url_path_tokens(url: Any) -> List[str]:
    if isinstance(url, dict):
        path = url.get("path")

        if isinstance(path, list):
            return [str(part).strip("/") for part in path if str(part).strip("/")]

        if isinstance(path, str):
            return [part for part in path.strip("/").split("/") if part]

        raw = url.get("raw")
        if isinstance(raw, str):
            return raw_path_tokens(raw)

        return []

    if isinstance(url, str):
        return raw_path_tokens(url)

    return []


def raw_path_tokens(raw: str) -> List[str]:
    raw = raw.strip()
    if raw == "":
        return []

    without_query = raw.split("?", 1)[0]

    parsed = urlsplit(without_query)
    if parsed.path:
        return [part for part in parsed.path.strip("/").split("/") if part]

    if "}}" in without_query:
        suffix = without_query.split("}}", 1)[1]
        return [part for part in suffix.strip("/").split("/") if part]

    if without_query.startswith("/"):
        return [part for part in without_query.strip("/").split("/") if part]

    if "/" in without_query:
        suffix = without_query.split("/", 1)[1]
        return [part for part in suffix.strip("/").split("/") if part]

    return [without_query] if without_query else []


def extract_variables(payload: dict) -> List[dict]:
    variables: List[dict] = []

    candidate = payload.get("variables")
    if not isinstance(candidate, list):
        return variables

    for var in candidate:
        if isinstance(var, dict):
            variables.append(var)

    return variables


def load_json(path: Path) -> dict:
    return json.loads(path.read_text())


def load_legacy_records(source_dir: Path, base_file_name: str) -> Dict[str, Any]:
    folder_records: List[dict] = []
    request_records: List[dict] = []
    variable_records: List[dict] = []

    files = 0

    for path in sorted(source_dir.glob("*.postman.json")):
        if path.name == base_file_name:
            continue

        files += 1
        payload = load_json(path)
        source = str(path.relative_to(source_dir))

        stem = path.name[: -len(".postman.json")]
        raw_tokens = [token for token in stem.split(".") if token]
        path_tokens = canonicalize_tokens(raw_tokens)

        order = parse_optional_int(payload.get("order"))
        requests_order = parse_optional_int(payload.get("requests_order"))

        folder_records.append({
            "path_tokens": path_tokens,
            "raw_tokens": raw_tokens,
            "name": payload.get("name"),
            "order": order,
            "requests_order": requests_order,
            "priority": LEGACY_PRIORITY,
            "source": source,
        })

        variables = extract_variables(payload)
        if variables:
            variable_records.append({
                "priority": LEGACY_PRIORITY,
                "source": source,
                "variables": variables,
            })

        items = payload.get("item", [])
        if not isinstance(items, list):
            continue

        default_request_order = requests_order if requests_order is not None else order

        for idx, item in enumerate(items):
            if not isinstance(item, dict):
                continue

            request_records.append({
                "path_tokens": path_tokens,
                "raw_tokens": raw_tokens,
                "item": item,
                "order": default_request_order,
                "sort_key": f"{source}:{idx:06d}",
                "priority": LEGACY_PRIORITY,
                "source": source,
            })

    return {
        "files": files,
        "folders": folder_records,
        "requests": request_records,
        "variables": variable_records,
    }


def load_tree_records(source_dir: Path, strict: bool = False) -> Dict[str, Any]:
    folder_records: List[dict] = []
    request_records: List[dict] = []
    variable_records: List[dict] = []

    index_files = 0
    endpoint_files = 0

    for root_name in ("central", "tenant"):
        root_dir = source_dir / root_name
        if not root_dir.exists():
            continue

        for path in sorted(root_dir.rglob("*.postman.json")):
            source = str(path.relative_to(source_dir))
            payload = load_json(path)

            if path.name == "_index.postman.json":
                index_files += 1

                raw_tokens = list(path.relative_to(source_dir).parts[:-1])
                path_tokens = canonicalize_tokens(raw_tokens)

                folder_records.append({
                    "path_tokens": path_tokens,
                    "raw_tokens": raw_tokens,
                    "name": payload.get("name"),
                    "order": parse_optional_int(payload.get("order")),
                    "requests_order": parse_optional_int(payload.get("requests_order")),
                    "priority": TREE_INDEX_PRIORITY,
                    "source": source,
                })

                variables = extract_variables(payload)
                if variables:
                    variable_records.append({
                        "priority": TREE_INDEX_PRIORITY,
                        "source": source,
                        "variables": variables,
                    })

                continue

            endpoint_files += 1

            raw_tokens = list(path.relative_to(source_dir).parts[:-1])
            path_tokens = canonicalize_tokens(raw_tokens)
            file_stem = path.name[: -len(".postman.json")]

            item = payload.get("item")
            if not isinstance(item, dict):
                continue

            top_level_name = payload.get("name")
            if isinstance(top_level_name, str) and top_level_name.strip() != "":
                item = {
                    **item,
                    "name": top_level_name,
                }

            if strict:
                method_token, separator, _ = file_stem.partition(".")
                if separator == "" or method_token.lower() not in HTTP_METHODS:
                    raise SystemExit(
                        f"Invalid endpoint filename '{source}'. Expected 'method.leaf.postman.json'."
                    )

                request = item.get("request")
                request_method = ""
                if isinstance(request, dict):
                    request_method = str(request.get("method", "")).strip().lower()

                if request_method and request_method != method_token.lower():
                    raise SystemExit(
                        f"Method mismatch in '{source}': filename method '{method_token}' "
                        f"!= request.method '{request_method}'."
                    )

            request_records.append({
                "path_tokens": path_tokens,
                "raw_tokens": raw_tokens,
                "item": item,
                "order": parse_optional_int(payload.get("order")),
                "sort_key": endpoint_sort_key(file_stem),
                "priority": TREE_ENDPOINT_PRIORITY,
                "source": source,
            })

            variables = extract_variables(payload)
            if variables:
                variable_records.append({
                    "priority": TREE_ENDPOINT_PRIORITY,
                    "source": source,
                    "variables": variables,
                })

    return {
        "index_files": index_files,
        "endpoint_files": endpoint_files,
        "folders": folder_records,
        "requests": request_records,
        "variables": variable_records,
    }


def dedupe_requests(records: List[dict]) -> Tuple[List[dict], int]:
    chosen: Dict[str, dict] = {}
    no_identity: List[dict] = []
    dropped = 0

    for record in sorted(records, key=lambda value: (value["priority"], value["source"], value["sort_key"])):
        key = endpoint_identity_key(record["item"])
        if key is None:
            no_identity.append(record)
            continue

        existing = chosen.get(key)
        if existing is None:
            chosen[key] = record
            continue

        if record["priority"] >= existing["priority"]:
            chosen[key] = record
            dropped += 1
            continue

        dropped += 1

    selected = list(chosen.values()) + no_identity
    selected.sort(key=lambda value: (value["priority"], value["source"], value["sort_key"]))
    return selected, dropped


def ensure_folder_meta(
    metadata: Dict[Tuple[str, ...], dict],
    path_tokens: List[str],
    raw_tokens: Optional[List[str]] = None,
) -> dict:
    key = tuple(path_tokens)
    if key not in metadata:
        metadata[key] = {
            "name": None,
            "order": None,
            "requests_order": None,
            "fallback_name": None,
        }

    entry = metadata[key]

    if entry["fallback_name"] is None and path_tokens:
        raw_token = path_tokens[-1]
        if raw_tokens and len(raw_tokens) == len(path_tokens):
            raw_token = raw_tokens[-1]
        entry["fallback_name"] = display_name_from_token(raw_token)

    return entry


def build_folder_metadata(folder_records: List[dict], request_records: List[dict]) -> Dict[Tuple[str, ...], dict]:
    metadata: Dict[Tuple[str, ...], dict] = {}

    for record in sorted(folder_records, key=lambda value: (value["priority"], value["source"])):
        path_tokens = record["path_tokens"]
        raw_tokens = record.get("raw_tokens")

        for index in range(1, len(path_tokens) + 1):
            ensure_folder_meta(metadata, path_tokens[:index], raw_tokens[:index] if raw_tokens else None)

        entry = ensure_folder_meta(metadata, path_tokens, raw_tokens)

        name = record.get("name")
        if isinstance(name, str) and name.strip() != "":
            entry["name"] = name

        order = record.get("order")
        if order is not None:
            entry["order"] = order

        requests_order = record.get("requests_order")
        if requests_order is not None:
            entry["requests_order"] = requests_order

    for record in request_records:
        path_tokens = record["path_tokens"]
        raw_tokens = record.get("raw_tokens")

        for index in range(1, len(path_tokens) + 1):
            ensure_folder_meta(metadata, path_tokens[:index], raw_tokens[:index] if raw_tokens else None)

    return metadata


def ensure_node(root: Node, path_tokens: List[str]) -> Node:
    node = root
    for token in path_tokens:
        if token not in node.children:
            node.children[token] = Node(token)
        node = node.children[token]
    return node


def build_items(node: Node) -> List[dict]:
    entries: List[Tuple[int, int, str, int, dict]] = []

    for child in node.children.values():
        child_name = child.name or child.fallback_name or display_name_from_token(child.token)
        entries.append((child.order if child.order is not None else DEFAULT_ORDER, 0, child_name.lower(), 0, {
            "name": child_name,
            "item": build_items(child),
        }))

    for order, sort_key, idx, item in node.requests:
        entries.append((order, 1, sort_key, idx, item))

    entries.sort(key=lambda entry: (entry[0], entry[1], entry[2], entry[3]))

    return [entry[4] for entry in entries]


def finalize_order(node: Node) -> None:
    for child in node.children.values():
        finalize_order(child)

    if node.name is None:
        node.name = node.fallback_name or display_name_from_token(node.token)

    if node.order is None:
        child_orders = [child.order for child in node.children.values() if child.order is not None]
        if child_orders:
            node.order = min(child_orders)


def merge_variables(base_vars: List[dict], variable_records: List[dict]) -> List[dict]:
    merged: Dict[str, dict] = {}

    for var in base_vars:
        if not isinstance(var, dict):
            continue

        key = var.get("key")
        if isinstance(key, str) and key != "":
            merged[key] = var

    for record in sorted(variable_records, key=lambda value: (value["priority"], value["source"])):
        for var in record.get("variables", []):
            if not isinstance(var, dict):
                continue

            key = var.get("key")
            if not isinstance(key, str) or key == "":
                continue
            merged[key] = var

    return list(merged.values())


def main() -> None:
    parser = argparse.ArgumentParser(description="Merge modular Postman files into postman.json")
    parser.add_argument("--source", default="docs/postman", help="Directory with module files")
    parser.add_argument("--base", default="collection.postman.json", help="Base collection file name")
    parser.add_argument("--output", default="postman.json", help="Output Postman collection path")
    parser.add_argument(
        "--mode",
        default="dual",
        choices=["legacy", "dual", "tree"],
        help="Merge mode: legacy dotted files, tree files, or both.",
    )
    parser.add_argument(
        "--strict",
        action="store_true",
        help="Validate tree endpoint filename method against item.request.method.",
    )
    parser.add_argument(
        "--report",
        action="store_true",
        help="Print merge summary.",
    )
    args = parser.parse_args()

    source_dir = Path(args.source)
    base_path = source_dir / args.base

    if not base_path.exists():
        raise SystemExit(f"Base file not found: {base_path}")

    base = load_json(base_path)

    folder_records: List[dict] = []
    request_records: List[dict] = []
    variable_records: List[dict] = []

    legacy_stats = {
        "files": 0,
    }
    tree_stats = {
        "index_files": 0,
        "endpoint_files": 0,
    }

    if args.mode in {"legacy", "dual"}:
        legacy = load_legacy_records(source_dir, args.base)
        folder_records.extend(legacy["folders"])
        request_records.extend(legacy["requests"])
        variable_records.extend(legacy["variables"])
        legacy_stats["files"] = legacy["files"]

    if args.mode in {"tree", "dual"}:
        tree = load_tree_records(source_dir, strict=args.strict)
        folder_records.extend(tree["folders"])
        request_records.extend(tree["requests"])
        variable_records.extend(tree["variables"])
        tree_stats["index_files"] = tree["index_files"]
        tree_stats["endpoint_files"] = tree["endpoint_files"]

    dropped_duplicates = 0
    if args.mode == "legacy":
        selected_requests = request_records
    else:
        selected_requests, dropped_duplicates = dedupe_requests(request_records)

    folder_metadata = build_folder_metadata(folder_records, selected_requests)

    root = Node("root")
    for path_tuple, metadata in folder_metadata.items():
        node = ensure_node(root, list(path_tuple))
        node.fallback_name = metadata["fallback_name"]

        if metadata["name"] is not None:
            node.name = metadata["name"]

        node.order = metadata["order"]
        node.requests_order = metadata["requests_order"]

    for index, request_record in enumerate(selected_requests):
        node = ensure_node(root, request_record["path_tokens"])
        order = request_record["order"]
        if order is None:
            if node.requests_order is not None:
                order = node.requests_order
            elif node.order is not None:
                order = node.order
            else:
                order = DEFAULT_ORDER

        node.requests.append((order, request_record["sort_key"], index, request_record["item"]))

    finalize_order(root)

    items = build_items(root)

    base["item"] = items
    base["variable"] = merge_variables(base.get("variable", []), variable_records)

    output_path = Path(args.output)
    output_path.write_text(json.dumps(base, indent=2))

    if args.report:
        print(f"mode={args.mode}")
        print(f"legacy_files={legacy_stats['files']}")
        print(f"tree_index_files={tree_stats['index_files']}")
        print(f"tree_endpoint_files={tree_stats['endpoint_files']}")
        print(f"folders={len(folder_metadata)}")
        print(f"requests={len(selected_requests)}")
        if args.mode != "legacy":
            print(f"dropped_duplicates={dropped_duplicates}")


if __name__ == "__main__":
    main()
