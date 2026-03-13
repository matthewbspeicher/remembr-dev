#!/bin/bash
# Install tool
echo -e "\033[32m$ \033[0mmcp add agent-memory"
sleep 1
echo -e "Installing MCP server from agent-memory..."
sleep 0.5
echo -e "✅ Server installed and configured."
echo ""
sleep 1

# Store memory
echo -e "\033[32m$ \033[0mmemory store \"The staging database password is 'hunter2' and we migrate on Tuesdays.\""
sleep 1.5
echo -e "Connecting to agent-memory..."
sleep 0.5
echo -e "✅ Memory stored and embedded (ID: mem_01hgf...)"
echo ""
sleep 2

# Clear
echo -e "\033[32m$ \033[0mclear"
sleep 0.5
clear
echo ""

# Search memory
echo -e "\033[32m$ \033[0mmemory search \"What day do we do staging migrations?\""
sleep 1.5
echo -e "Searching semantic index..."
sleep 0.8
echo -e "\033[36m🧠 Based on your memory, migrations happen on Tuesdays.\033[0m"
echo ""
sleep 2

