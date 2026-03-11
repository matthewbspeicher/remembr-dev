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
  
  try {
    const res = await fetch(`${BASE_URL}/api/v1${path}`, opts);
    let data;
    
    // Check if the response has JSON content
    const contentType = res.headers.get("content-type");
    if (contentType && contentType.includes("application/json")) {
      data = await res.json();
    } else {
      data = { message: await res.text() || `API error ${res.status}` };
    }
    
    if (!res.ok) throw new Error(data.message || `API error ${res.status}`);
    return data;
  } catch (error) {
    if (error.message.startsWith("API error") || error.message.includes("failed") || error.message.includes("Failed to fetch") || error.message.includes("fetch failed")) {
       throw error;
    }
    throw new Error(`Network error communicating with Remembr API: ${error.message}`);
  }
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
    ttl: z.string().optional().describe("Optional shorthand time-to-live (e.g., '24h', '7d', '30m')"),
    tags: z.array(z.string()).max(10).optional().describe("Optional array of tags (max 10)"),
  },
  async ({ value, key, visibility, metadata, expires_at, ttl, tags }) => {
    const body = { value, visibility };
    if (key) body.key = key;
    if (metadata) body.metadata = metadata;
    if (expires_at) body.expires_at = expires_at;
    if (ttl) body.ttl = ttl;
    if (tags) body.tags = tags;
    const result = await api("POST", "/memories", body);
    return { content: [{ type: "text", text: JSON.stringify(result, null, 2) }] };
  }
);

server.tool(
  "update_memory",
  "Update an existing memory by key",
  {
    key: z.string().describe("The memory key to update"),
    value: z.string().optional().describe("The new memory content"),
    visibility: z.enum(["private", "shared", "public"]).optional().describe("New visibility setting"),
    metadata: z.record(z.any()).optional().describe("New metadata object (will replace existing)"),
    expires_at: z.string().optional().describe("New ISO 8601 expiry timestamp"),
    ttl: z.string().optional().describe("New shorthand time-to-live (e.g., '24h', '7d', '30m')"),
    tags: z.array(z.string()).max(10).optional().describe("New array of tags (max 10)"),
  },
  async ({ key, value, visibility, metadata, expires_at, ttl, tags }) => {
    const body = {};
    if (value !== undefined) body.value = value;
    if (visibility !== undefined) body.visibility = visibility;
    if (metadata !== undefined) body.metadata = metadata;
    if (expires_at !== undefined) body.expires_at = expires_at;
    if (ttl !== undefined) body.ttl = ttl;
    if (tags !== undefined) body.tags = tags;
    
    if (Object.keys(body).length === 0) {
      throw new Error("Must provide at least one field to update");
    }
    
    const result = await api("PATCH", `/memories/${encodeURIComponent(key)}`, body);
    return { content: [{ type: "text", text: JSON.stringify(result, null, 2) }] };
  }
);

server.tool(
  "search_memories",
  "Semantic search across your own memories",
  {
    q: z.string().describe("Natural language search query"),
    limit: z.number().default(10).describe("Max results to return"),
    tags: z.string().optional().describe("Comma-separated list of tags to filter by"),
  },
  async ({ q, limit, tags }) => {
    const tagsParam = tags ? `&tags=${encodeURIComponent(tags)}` : '';
    const result = await api("GET", `/memories/search?q=${encodeURIComponent(q)}&limit=${limit}${tagsParam}`);
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
    tags: z.string().optional().describe("Comma-separated list of tags to filter by"),
  },
  async ({ page, tags }) => {
    const tagsParam = tags ? `&tags=${encodeURIComponent(tags)}` : '';
    const result = await api("GET", `/memories?page=${page}${tagsParam}`);
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
    tags: z.string().optional().describe("Comma-separated list of tags to filter by"),
  },
  async ({ q, limit, tags }) => {
    const tagsParam = tags ? `&tags=${encodeURIComponent(tags)}` : '';
    const result = await api("GET", `/commons/search?q=${encodeURIComponent(q)}&limit=${limit}${tagsParam}`);
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
