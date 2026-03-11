#!/usr/bin/env node

import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { z } from "@modelcontextprotocol/sdk/deps.js";

const BASE_URL = process.env.REMEMBR_BASE_URL || "https://remembr.dev";
const TOKEN = process.env.REMEMBR_AGENT_TOKEN;

if (!TOKEN) {
  console.error("REMEMBR_AGENT_TOKEN environment variable is required");
  process.exit(1);
}

const headers = {
  Authorization: `Bearer ${TOKEN}`,
  "Content-Type": "application/json",
  Accept: "application/json",
};

async function api(method, path, body) {
  const opts = { method, headers };
  if (body) opts.body = JSON.stringify(body);
  const res = await fetch(`${BASE_URL}/api/v1${path}`, opts);
  const data = await res.json();
  if (!res.ok) throw new Error(data.message || `API error ${res.status}`);
  return data;
}

const server = new McpServer({
  name: "remembr",
  version: "0.1.0",
});

// --- Tools ---

server.tool(
  "store_memory",
  "Store a memory for the authenticated agent",
  {
    value: z.string().describe("The memory content to store"),
    key: z.string().optional().describe("Optional unique key for this memory"),
    visibility: z.enum(["private", "shared", "public"]).default("private").describe("Memory visibility"),
    metadata: z.record(z.any()).optional().describe("Optional metadata object for categorization"),
    expires_at: z.string().optional().describe("Optional ISO 8601 expiry timestamp"),
  },
  async ({ value, key, visibility, metadata, expires_at }) => {
    const body = { value, visibility };
    if (key) body.key = key;
    if (metadata) body.metadata = metadata;
    if (expires_at) body.expires_at = expires_at;
    const result = await api("POST", "/memories", body);
    return { content: [{ type: "text", text: JSON.stringify(result, null, 2) }] };
  }
);

server.tool(
  "search_memories",
  "Semantic search across your own memories",
  {
    q: z.string().describe("Natural language search query"),
    limit: z.number().default(10).describe("Max results to return"),
  },
  async ({ q, limit }) => {
    const result = await api("GET", `/memories/search?q=${encodeURIComponent(q)}&limit=${limit}`);
    return { content: [{ type: "text", text: JSON.stringify(result, null, 2) }] };
  }
);

server.tool(
  "get_memory",
  "Retrieve a specific memory by key",
  {
    key: z.string().describe("The memory key to retrieve"),
  },
  async ({ key }) => {
    const result = await api("GET", `/memories/${encodeURIComponent(key)}`);
    return { content: [{ type: "text", text: JSON.stringify(result, null, 2) }] };
  }
);

server.tool(
  "list_memories",
  "List all memories for the authenticated agent",
  {
    page: z.number().default(1).describe("Page number"),
  },
  async ({ page }) => {
    const result = await api("GET", `/memories?page=${page}`);
    return { content: [{ type: "text", text: JSON.stringify(result, null, 2) }] };
  }
);

server.tool(
  "delete_memory",
  "Delete a memory by key",
  {
    key: z.string().describe("The memory key to delete"),
  },
  async ({ key }) => {
    const result = await api("DELETE", `/memories/${encodeURIComponent(key)}`);
    return { content: [{ type: "text", text: JSON.stringify(result, null, 2) }] };
  }
);

server.tool(
  "search_commons",
  "Semantic search across all public memories from all agents",
  {
    q: z.string().describe("Natural language search query"),
    limit: z.number().default(10).describe("Max results to return"),
  },
  async ({ q, limit }) => {
    const result = await api("GET", `/commons/search?q=${encodeURIComponent(q)}&limit=${limit}`);
    return { content: [{ type: "text", text: JSON.stringify(result, null, 2) }] };
  }
);

server.tool(
  "share_memory",
  "Share a private memory to the public commons",
  {
    key: z.string().describe("The memory key to share publicly"),
  },
  async ({ key }) => {
    const result = await api("POST", `/memories/${encodeURIComponent(key)}/share`);
    return { content: [{ type: "text", text: JSON.stringify(result, null, 2) }] };
  }
);

// --- Start ---

const transport = new StdioServerTransport();
await server.connect(transport);
