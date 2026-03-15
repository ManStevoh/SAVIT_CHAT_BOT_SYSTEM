'use client'

import { useState, useCallback } from 'react'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Skeleton } from '@/components/ui/skeleton'
import { StatusBadge } from '@/components/shared/status-badge'
import { useChats, useMessages, useCustomers } from '@/lib/api-hooks'
import { sendMessage } from '@/lib/api-actions'
import type { Chat, Message, Customer } from '@/lib/mock-data'
import {
  Search,
  Send,
  Paperclip,
  MoreVertical,
  Phone,
  Video,
  ShoppingCart,
  Tag,
  Clock,
  Bot,
  MessageSquare,
  User,
  AlertCircle,
} from 'lucide-react'
import { useSWRConfig } from 'swr'

export default function ChatsPage() {
  const { mutate } = useSWRConfig()
  const [searchQuery, setSearchQuery] = useState('')
  const [statusFilter, setStatusFilter] = useState<string>('all')
  const [selectedChatId, setSelectedChatId] = useState<string | null>(null)
  const [messageInput, setMessageInput] = useState('')
  const [isSending, setIsSending] = useState(false)

  // API Hooks - Replace with actual API endpoints
  // TODO: Connect to GET /api/company/chats
  const {
    data: chats,
    isLoading: chatsLoading,
    error: chatsError,
  } = useChats({ status: statusFilter, search: searchQuery })

  // TODO: Connect to GET /api/company/chats/:chatId/messages
  const {
    data: messages,
    isLoading: messagesLoading,
  } = useMessages(selectedChatId)

  // Get selected chat details
  const selectedChat = chats?.find((c) => c.id === selectedChatId) || (chats?.[0] ?? null)

  // Auto-select first chat if none selected
  if (!selectedChatId && chats && chats.length > 0) {
    setSelectedChatId(chats[0].id)
  }

  // Handle sending message
  // TODO: Connect to POST /api/company/chats/:chatId/messages
  const handleSendMessage = useCallback(async () => {
    if (!messageInput.trim() || !selectedChatId) return

    setIsSending(true)
    try {
      const result = await sendMessage({
        chatId: selectedChatId,
        content: messageInput,
      })

      if (result.success) {
        setMessageInput('')
        // Revalidate messages to show new message
        mutate(['messages', selectedChatId])
      }
    } catch (error) {
      console.error('Failed to send message:', error)
    } finally {
      setIsSending(false)
    }
  }, [messageInput, selectedChatId, mutate])

  // Handle keyboard shortcut for sending
  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault()
      handleSendMessage()
    }
  }

  return (
    <div className="flex h-[calc(100vh-7rem)] gap-4">
      {/* Conversations List */}
      <div className="w-80 shrink-0 overflow-hidden rounded-xl border border-border/50 bg-card flex flex-col">
        <div className="border-b border-border/50 p-4">
          <h2 className="mb-3 font-semibold text-foreground">Conversations</h2>
          <div className="relative">
            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              placeholder="Search conversations..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="bg-background border-border/50 pl-10"
            />
          </div>
          {/* Status Filter Tabs */}
          <div className="mt-3 flex gap-2">
            {['all', 'active', 'pending', 'resolved'].map((status) => (
              <button
                key={status}
                onClick={() => setStatusFilter(status)}
                className={`rounded-full px-3 py-1 text-xs font-medium transition-colors ${
                  statusFilter === status
                    ? 'bg-primary text-primary-foreground'
                    : 'bg-secondary/50 text-muted-foreground hover:bg-secondary'
                }`}
              >
                {status.charAt(0).toUpperCase() + status.slice(1)}
              </button>
            ))}
          </div>
        </div>

        <ScrollArea className="flex-1">
          <div className="p-2">
            {/* Loading State */}
            {chatsLoading && (
              <div className="space-y-2">
                {[...Array(5)].map((_, i) => (
                  <div key={i} className="flex items-start gap-3 rounded-lg p-3">
                    <Skeleton className="h-10 w-10 rounded-full" />
                    <div className="flex-1 space-y-2">
                      <Skeleton className="h-4 w-24" />
                      <Skeleton className="h-3 w-full" />
                    </div>
                  </div>
                ))}
              </div>
            )}

            {/* Error State */}
            {chatsError && (
              <div className="flex flex-col items-center justify-center p-8 text-center">
                <AlertCircle className="h-10 w-10 text-destructive" />
                <p className="mt-2 text-sm text-destructive">Failed to load chats</p>
                <Button
                  variant="outline"
                  size="sm"
                  className="mt-4"
                  onClick={() => mutate(['chats', { status: statusFilter, search: searchQuery }])}
                >
                  Retry
                </Button>
              </div>
            )}

            {/* Empty State */}
            {!chatsLoading && !chatsError && (!chats || chats.length === 0) && (
              <div className="flex flex-col items-center justify-center p-8 text-center">
                <MessageSquare className="h-10 w-10 text-muted-foreground/50" />
                <p className="mt-2 font-medium text-foreground">No conversations</p>
                <p className="text-sm text-muted-foreground">
                  {searchQuery ? 'Try a different search' : 'Chats will appear here'}
                </p>
              </div>
            )}

            {/* Chat List */}
            {!chatsLoading &&
              chats?.map((chat) => (
                <button
                  key={chat.id}
                  onClick={() => setSelectedChatId(chat.id)}
                  className={`flex w-full items-start gap-3 rounded-lg p-3 text-left transition-colors ${
                    selectedChat?.id === chat.id
                      ? 'bg-primary/10'
                      : 'hover:bg-muted/5'
                  }`}
                >
                  <div className="relative">
                    <div className="flex h-10 w-10 items-center justify-center rounded-full bg-secondary text-sm font-medium text-secondary-foreground">
                      {chat.customerAvatar ? (
                        <img
                          src={chat.customerAvatar}
                          alt={chat.customerName}
                          className="h-full w-full rounded-full object-cover"
                        />
                      ) : (
                        chat.customerName.charAt(0)
                      )}
                    </div>
                    {chat.status === 'active' && (
                      <span className="absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full border-2 border-card bg-primary" />
                    )}
                  </div>
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center justify-between">
                      <span className="truncate font-medium text-foreground">
                        {chat.customerName}
                      </span>
                      <span className="text-xs text-muted-foreground">
                        {chat.lastMessageTime}
                      </span>
                    </div>
                    <div className="flex items-center gap-2">
                      {chat.aiHandled && (
                        <Bot className="h-3 w-3 text-primary shrink-0" />
                      )}
                      <p className="truncate text-sm text-muted-foreground">
                        {chat.lastMessage}
                      </p>
                    </div>
                  </div>
                  {chat.unreadCount > 0 && (
                    <span className="flex h-5 w-5 items-center justify-center rounded-full bg-primary text-[10px] font-bold text-primary-foreground">
                      {chat.unreadCount}
                    </span>
                  )}
                </button>
              ))}
          </div>
        </ScrollArea>
      </div>

      {/* Chat Area */}
      <div className="flex flex-1 overflow-hidden rounded-xl border border-border/50 bg-card flex-col">
        {selectedChat ? (
          <>
            {/* Chat Header */}
            <div className="flex items-center justify-between border-b border-border/50 px-6 py-4">
              <div className="flex items-center gap-3">
                <div className="relative">
                  <div className="flex h-10 w-10 items-center justify-center rounded-full bg-secondary text-sm font-medium text-secondary-foreground">
                    {selectedChat.customerName.charAt(0)}
                  </div>
                  {selectedChat.status === 'active' && (
                    <span className="absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full border-2 border-card bg-primary" />
                  )}
                </div>
                <div>
                  <div className="font-medium text-foreground">
                    {selectedChat.customerName}
                  </div>
                  <div className="flex items-center gap-2">
                    {selectedChat.aiHandled && (
                      <div className="flex items-center gap-1 text-xs text-primary">
                        <Bot className="h-3 w-3" />
                        AI is handling
                      </div>
                    )}
                    <StatusBadge status={selectedChat.status} />
                  </div>
                </div>
              </div>
              <div className="flex items-center gap-2">
                <Button variant="ghost" size="icon">
                  <Phone className="h-4 w-4" />
                </Button>
                <Button variant="ghost" size="icon">
                  <Video className="h-4 w-4" />
                </Button>
                <Button variant="ghost" size="icon">
                  <MoreVertical className="h-4 w-4" />
                </Button>
              </div>
            </div>

            {/* Messages Area */}
            <ScrollArea className="flex-1 p-6">
              {/* Loading State */}
              {messagesLoading && (
                <div className="space-y-4">
                  {[...Array(4)].map((_, i) => (
                    <div
                      key={i}
                      className={`flex ${i % 2 === 0 ? 'justify-end' : 'justify-start'}`}
                    >
                      <Skeleton className="h-16 w-64 rounded-2xl" />
                    </div>
                  ))}
                </div>
              )}

              {/* Empty State */}
              {!messagesLoading && (!messages || messages.length === 0) && (
                <div className="flex h-full flex-col items-center justify-center text-center">
                  <MessageSquare className="h-12 w-12 text-muted-foreground/50" />
                  <p className="mt-4 font-medium text-foreground">No messages yet</p>
                  <p className="text-sm text-muted-foreground">
                    Start the conversation by sending a message
                  </p>
                </div>
              )}

              {/* Messages List */}
              {!messagesLoading && messages && messages.length > 0 && (
                <div className="space-y-4">
                  {messages.map((msg) => (
                    <div
                      key={msg.id}
                      className={`flex ${
                        msg.sender === 'customer' ? 'justify-end' : 'justify-start'
                      }`}
                    >
                      <div
                        className={`max-w-[70%] rounded-2xl px-4 py-2 ${
                          msg.sender === 'customer'
                            ? 'rounded-br-md bg-primary text-primary-foreground'
                            : 'rounded-bl-md bg-secondary text-secondary-foreground'
                        }`}
                      >
                        {msg.sender === 'bot' && (
                          <div className="mb-1 flex items-center gap-1 text-xs text-primary">
                            <Bot className="h-3 w-3" />
                            AI Assistant
                          </div>
                        )}
                        {msg.sender === 'agent' && (
                          <div className="mb-1 flex items-center gap-1 text-xs text-blue-500">
                            <User className="h-3 w-3" />
                            Agent
                          </div>
                        )}
                        <p className="text-sm">{msg.content}</p>
                        <span
                          className={`mt-1 block text-[10px] ${
                            msg.sender === 'customer'
                              ? 'text-primary-foreground/70'
                              : 'text-muted-foreground'
                          }`}
                        >
                          {msg.timestamp}
                        </span>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </ScrollArea>

            {/* Message Input */}
            <div className="border-t border-border/50 p-4">
              <div className="flex items-center gap-2">
                <Button variant="ghost" size="icon">
                  <Paperclip className="h-4 w-4" />
                </Button>
                <Input
                  placeholder="Type a message..."
                  value={messageInput}
                  onChange={(e) => setMessageInput(e.target.value)}
                  onKeyDown={handleKeyDown}
                  disabled={isSending}
                  className="flex-1 bg-background border-border/50"
                />
                <Button
                  size="icon"
                  onClick={handleSendMessage}
                  disabled={!messageInput.trim() || isSending}
                >
                  <Send className="h-4 w-4" />
                </Button>
              </div>
              <p className="mt-2 text-xs text-muted-foreground">
                Press Enter to send, Shift+Enter for new line
              </p>
            </div>
          </>
        ) : (
          // No chat selected state
          <div className="flex h-full flex-col items-center justify-center text-center">
            <MessageSquare className="h-16 w-16 text-muted-foreground/30" />
            <p className="mt-4 text-lg font-medium text-foreground">
              Select a conversation
            </p>
            <p className="text-muted-foreground">
              Choose a chat from the list to view messages
            </p>
          </div>
        )}
      </div>

      {/* Customer Details Panel */}
      <div className="w-80 shrink-0 overflow-hidden rounded-xl border border-border/50 bg-card">
        {selectedChat ? (
          <>
            <div className="border-b border-border/50 p-6 text-center">
              <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-secondary text-xl font-medium text-secondary-foreground">
                {selectedChat.customerName.charAt(0)}
              </div>
              <h3 className="mt-3 font-semibold text-foreground">
                {selectedChat.customerName}
              </h3>
              <p className="text-sm text-muted-foreground">
                {selectedChat.customerPhone}
              </p>
              <StatusBadge status={selectedChat.status} className="mt-2" />
            </div>

            <div className="space-y-4 p-4">
              {/* Tags Section */}
              <div>
                <h4 className="mb-2 flex items-center gap-2 text-xs font-medium uppercase tracking-wider text-muted-foreground">
                  <Tag className="h-3 w-3" />
                  Tags
                </h4>
                <div className="flex flex-wrap gap-2">
                  {selectedChat.aiHandled && (
                    <Badge variant="secondary">AI Handled</Badge>
                  )}
                  <Badge variant="outline" className="border-border/50">
                    + Add Tag
                  </Badge>
                </div>
              </div>

              {/* Quick Actions */}
              <div>
                <h4 className="mb-2 flex items-center gap-2 text-xs font-medium uppercase tracking-wider text-muted-foreground">
                  <ShoppingCart className="h-3 w-3" />
                  Quick Actions
                </h4>
                <div className="space-y-2">
                  <Button variant="outline" className="w-full justify-start border-border/50">
                    <ShoppingCart className="mr-2 h-4 w-4" />
                    Create Order
                  </Button>
                  <Button variant="outline" className="w-full justify-start border-border/50">
                    <User className="mr-2 h-4 w-4" />
                    View Customer Profile
                  </Button>
                </div>
              </div>

              {/* Conversation Stats */}
              <div>
                <h4 className="mb-2 flex items-center gap-2 text-xs font-medium uppercase tracking-wider text-muted-foreground">
                  <Clock className="h-3 w-3" />
                  Conversation Stats
                </h4>
                <div className="space-y-2 rounded-lg bg-secondary/30 p-3">
                  <div className="flex items-center justify-between text-sm">
                    <span className="text-muted-foreground">Messages</span>
                    <span className="font-medium text-foreground">
                      {messages?.length || 0}
                    </span>
                  </div>
                  <div className="flex items-center justify-between text-sm">
                    <span className="text-muted-foreground">Status</span>
                    <StatusBadge status={selectedChat.status} />
                  </div>
                  <div className="flex items-center justify-between text-sm">
                    <span className="text-muted-foreground">Unread</span>
                    <span className="font-medium text-foreground">
                      {selectedChat.unreadCount}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </>
        ) : (
          <div className="flex h-full flex-col items-center justify-center p-4 text-center">
            <User className="h-12 w-12 text-muted-foreground/30" />
            <p className="mt-4 font-medium text-foreground">No chat selected</p>
            <p className="text-sm text-muted-foreground">
              Select a conversation to view customer details
            </p>
          </div>
        )}
      </div>
    </div>
  )
}
