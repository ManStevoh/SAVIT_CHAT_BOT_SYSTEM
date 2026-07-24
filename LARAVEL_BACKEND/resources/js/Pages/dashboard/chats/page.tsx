'use client'

import { useState, useCallback, useEffect } from 'react'
import { useSearchParams, useRouter } from 'next/navigation'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Skeleton } from '@/components/ui/skeleton'
import { StatusBadge } from '@/components/shared/status-badge'
import { useChats, useMessages, useProducts } from '@/lib/api-hooks'
import { sendMessage, handBackToBot, createOrderFromChat, submitMessageLearningFeedback } from '@/lib/api-actions'
import type { Chat, Message, Customer } from '@/lib/mock-data'
import {
  Search,
  Send,
  Paperclip,
  ArrowLeft,
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
  X,
  ThumbsUp,
  ThumbsDown,
} from 'lucide-react'
import { useSWRConfig } from 'swr'
import { useToast } from '@/hooks/use-toast'
import { FormModal } from '@/components/shared/modal'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { PageHeader } from '@/components/shared/page-header'

export default function ChatsPage() {
  const { toast } = useToast()
  const { mutate } = useSWRConfig()
  const router = useRouter()
  const searchParams = useSearchParams()
  const initialSearch = searchParams.get('search') ?? ''
  const chatIdFromUrl = searchParams.get('chat')
  const [searchQuery, setSearchQuery] = useState(initialSearch)
  const [statusFilter, setStatusFilter] = useState<string>('all')
  const [attributedOnly, setAttributedOnly] = useState(false)
  const [selectedChatId, setSelectedChatId] = useState<string | null>(null)
  const [messageInput, setMessageInput] = useState('')
  const [isSending, setIsSending] = useState(false)
  const [isHandingBack, setIsHandingBack] = useState(false)
  const [isMobile, setIsMobile] = useState(false)
  const [createOrderOpen, setCreateOrderOpen] = useState(false)
  const [selectedProductId, setSelectedProductId] = useState('')
  const [orderQuantity, setOrderQuantity] = useState('1')
  const [isCreatingOrder, setIsCreatingOrder] = useState(false)
  const [selectedAttachment, setSelectedAttachment] = useState<File | null>(null)
  const [feedbackBusy, setFeedbackBusy] = useState<string | null>(null)
  const { data: products = [], isLoading: productsLoading } = useProducts({ status: 'active' })

  const {
    data: chats,
    isLoading: chatsLoading,
    error: chatsError,
  } = useChats({ status: statusFilter, search: searchQuery, attributedOnly })

  const {
    data: messages,
    isLoading: messagesLoading,
  } = useMessages(selectedChatId)

  const selectedChat =
    chats?.find((c) => c.id === selectedChatId) || (!isMobile ? (chats?.[0] ?? null) : null)

  useEffect(() => {
    if (typeof window === 'undefined') return
    const media = window.matchMedia('(max-width: 767px)')
    const update = () => setIsMobile(media.matches)
    update()
    media.addEventListener('change', update)
    return () => media.removeEventListener('change', update)
  }, [])

  useEffect(() => {
    if (!chats?.length) return

    if (chatIdFromUrl) {
      const match = chats.find((c) => c.id === chatIdFromUrl)
      if (match) {
        setSelectedChatId(chatIdFromUrl)
        router.replace('/dashboard/chats', { scroll: false })
      }
      return
    }

    if (!isMobile && !selectedChatId) {
      setSelectedChatId(chats[0].id)
    }
  }, [chats, selectedChatId, isMobile, chatIdFromUrl, router])

  const handleSendMessage = useCallback(async () => {
    if ((!messageInput.trim() && !selectedAttachment) || !selectedChatId) return

    setIsSending(true)
    try {
      const result = await sendMessage({
        chatId: selectedChatId,
        content: messageInput,
        attachment: selectedAttachment ?? undefined,
      })

      if (result.success) {
        setMessageInput('')
        setSelectedAttachment(null)
        mutate(['messages', selectedChatId])
        if (result.whatsappSent === false && result.whatsappError) {
          toast({
            title: result.message ?? 'Message saved but not delivered',
            description: result.whatsappError,
            variant: 'destructive',
          })
        }
      }
    } catch (error) {
      console.error('Failed to send message:', error)
    } finally {
      setIsSending(false)
    }
  }, [messageInput, selectedAttachment, selectedChatId, mutate, toast])

  const handleHandBackToBot = useCallback(async () => {
    if (!selectedChatId) return
    setIsHandingBack(true)
    try {
      const result = await handBackToBot(selectedChatId)
      if (result.success) {
        mutate(['chats', { status: statusFilter, search: searchQuery }])
      }
    } catch (e) {
      console.error('Hand back failed:', e)
    } finally {
      setIsHandingBack(false)
    }
  }, [selectedChatId, statusFilter, searchQuery, mutate])

  // Handle keyboard shortcut for sending
  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault()
      handleSendMessage()
    }
  }

  const handleAttachmentSelected = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (!file) return
    setSelectedAttachment(file)
    toast({
      title: 'Attachment ready',
      description: `Selected "${file.name}". Send now to share it with this customer.`,
    })
    e.target.value = ''
  }

  const isAgentHandling = selectedChat?.agentHandlingAt != null

  const handleCreateOrder = useCallback(() => {
    if (!selectedChat) return
    setSelectedProductId('')
    setOrderQuantity('1')
    setCreateOrderOpen(true)
  }, [selectedChat])

  const handleViewCustomerProfile = useCallback(() => {
    if (!selectedChat) return
    router.push(`/dashboard/customers?search=${encodeURIComponent(selectedChat.customerPhone)}`)
  }, [router, selectedChat])

  const handleSubmitCreateOrder = useCallback(async () => {
    if (!selectedChatId) return
    const selectedProduct = products.find((p) => p.id === selectedProductId)
    const qty = Number.parseInt(orderQuantity, 10)
    if (!selectedProduct || !Number.isFinite(qty) || qty < 1) {
      toast({
        title: 'Invalid order details',
        description: 'Please select a product and quantity.',
        variant: 'destructive',
      })
      return
    }

    setIsCreatingOrder(true)
    try {
      const result = await createOrderFromChat({
        chatId: selectedChatId,
        items: [{
          productId: selectedProduct.id,
          name: selectedProduct.name,
          quantity: qty,
          price: Number(selectedProduct.price) || 0,
        }],
        sendWhatsApp: true,
      })
      if (result.success) {
        setCreateOrderOpen(false)
        toast({
          title: result.message ?? 'Order created',
          description: result.whatsappSent === false ? (result.whatsappError ?? 'WhatsApp delivery failed.') : 'Customer received invoice and payment prompt on WhatsApp.',
          variant: result.whatsappSent === false ? 'destructive' : 'default',
        })
        mutate(['orders', { status: 'all', search: '', page: 1, limit: 10 }])
        mutate(['messages', selectedChatId])
        mutate(['chats', { status: statusFilter, search: searchQuery }])
      } else {
        toast({
          title: 'Failed to create order',
          description: result.message ?? 'Please try again.',
          variant: 'destructive',
        })
      }
    } catch (e) {
      toast({
        title: 'Failed to create order',
        description: 'Unexpected error while creating the order.',
        variant: 'destructive',
      })
    } finally {
      setIsCreatingOrder(false)
    }
  }, [selectedChatId, products, selectedProductId, orderQuantity, toast, mutate, statusFilter, searchQuery])

  return (
    <div className="flex h-[calc(100dvh-8rem)] min-h-0 flex-col gap-5 lg:h-[calc(100vh-8rem)]">
      <PageHeader
        title="Chats"
        description="Manage customer conversations and agent handoff"
      />
      <div className="flex min-h-0 flex-1 gap-4">
      {/* Conversations List */}
      <div
        className={`${
          isMobile && selectedChat ? 'hidden' : 'flex'
        } h-full min-h-0 w-full shrink-0 flex-col overflow-hidden rounded-xl border border-border/60 bg-card shadow-sm md:w-80`}
      >
        <div className="border-b border-border/60 p-4">
          <h2 className="mb-3 text-sm font-medium text-foreground">Conversations</h2>
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
          <div className="mt-3 flex flex-wrap gap-2">
            <button
              onClick={() => setAttributedOnly((v) => !v)}
                className={`rounded-full px-3 py-1 text-xs font-medium transition-colors ${
                attributedOnly
                  ? 'bg-foreground text-background'
                  : 'bg-muted text-muted-foreground hover:bg-muted/80'
              }`}
            >
              From social
            </button>
            {['all', 'active', 'pending', 'resolved'].map((status) => (
              <button
                key={status}
                onClick={() => setStatusFilter(status)}
                className={`rounded-full px-3 py-1 text-xs font-medium transition-colors ${
                  statusFilter === status
                    ? 'bg-foreground text-background'
                    : 'bg-muted text-muted-foreground hover:bg-muted/80'
                }`}
              >
                {status.charAt(0).toUpperCase() + status.slice(1)}
              </button>
            ))}
          </div>
        </div>

        <ScrollArea className="min-h-0 flex-1">
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
                  className={`relative flex w-full items-start gap-3 rounded-lg p-3 text-left transition-colors ${
                    selectedChat?.id === chat.id
                      ? 'bg-muted ring-1 ring-border/60'
                      : 'hover:bg-muted/50'
                  }`}
                >
                  <div className="relative">
                    <div className="flex h-10 w-10 items-center justify-center rounded-full bg-muted text-sm font-medium text-foreground">
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
                      {chat.isAttributed && chat.attribution?.postTitle && (
                        <Badge variant="outline" className="text-[10px] shrink-0 max-w-[120px] truncate">
                          {chat.attribution.postTitle}
                        </Badge>
                      )}
                      <p className="truncate text-sm text-foreground/80">
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
      <div
        className={`${
          isMobile && !selectedChat ? 'hidden' : 'flex'
        } min-h-0 flex-1 flex-col overflow-hidden rounded-xl border border-border/60 bg-card shadow-sm`}
      >
        {selectedChat ? (
          <>
            {/* Chat Header */}
            <div className="flex shrink-0 flex-col gap-3 border-b border-border/50 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
              <div className="flex min-w-0 items-center gap-3">
                {isMobile && (
                  <Button
                    variant="ghost"
                    size="icon"
                    className="md:hidden"
                    onClick={() => setSelectedChatId(null)}
                  >
                    <ArrowLeft className="h-4 w-4" />
                  </Button>
                )}
                <div className="relative">
                  <div className="flex h-10 w-10 items-center justify-center rounded-full bg-secondary text-sm font-medium text-secondary-foreground">
                    {selectedChat.customerName.charAt(0)}
                  </div>
                  {selectedChat.status === 'active' && (
                    <span className="absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full border-2 border-card bg-primary" />
                  )}
                </div>
                <div className="min-w-0">
                  <div className="truncate font-medium text-foreground">
                    {selectedChat.customerName}
                  </div>
                  <div className="flex flex-wrap items-center gap-2">
                    {selectedChat.aiHandled && (
                      <div className="flex items-center gap-1 text-xs text-primary">
                        <Bot className="h-3 w-3" />
                        AI is handling
                      </div>
                    )}
                    {isAgentHandling && (
                      <div className="flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400">
                        <User className="h-3 w-3" />
                        Agent handling
                      </div>
                    )}
                    <StatusBadge status={selectedChat.status} />
                  </div>
                </div>
              </div>
              <div className="flex w-full items-center justify-end gap-2 sm:w-auto">
                <Button variant="ghost" size="icon" className="hidden md:inline-flex">
                  <Phone className="h-4 w-4" />
                </Button>
                <Button variant="ghost" size="icon" className="hidden md:inline-flex">
                  <Video className="h-4 w-4" />
                </Button>
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="ghost" size="icon" aria-label="Open chat actions">
                      <MoreVertical className="h-4 w-4" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="end">
                    <DropdownMenuItem onClick={handleCreateOrder}>
                      Create Order
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={handleViewCustomerProfile}>
                      View Customer Profile
                    </DropdownMenuItem>
                    {isAgentHandling && (
                      <DropdownMenuItem onClick={handleHandBackToBot} disabled={isHandingBack}>
                        {isHandingBack ? 'Handing back…' : 'Hand back to bot'}
                      </DropdownMenuItem>
                    )}
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>
            </div>

            {/* Messages Area */}
            <ScrollArea className="min-h-0 flex-1 p-6 overflow-x-auto">
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
                        className={`w-fit max-w-full rounded-2xl px-4 py-2 sm:max-w-[85%] lg:max-w-[70%] ${
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
                        <p className="text-sm whitespace-pre-wrap [overflow-wrap:anywhere] break-all">
                          {msg.content}
                        </p>
                        {msg.attachmentUrl && (
                          <div className="mt-2">
                            {msg.messageType === 'image' ? (
                              <a href={msg.attachmentUrl} target="_blank" rel="noopener noreferrer">
                                <img
                                  src={msg.attachmentUrl}
                                  alt={msg.attachmentName ?? 'Attachment'}
                                  className="max-h-52 max-w-full rounded-lg border border-border/50 object-contain"
                                />
                              </a>
                            ) : (
                              <a
                                href={msg.attachmentUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center rounded-md border border-border/50 px-2 py-1 text-xs hover:bg-background/40"
                              >
                                <Paperclip className="mr-1 h-3 w-3" />
                                {msg.attachmentName ?? 'Download attachment'}
                              </a>
                            )}
                          </div>
                        )}
                        <span
                          className={`mt-1 block text-[10px] ${
                            msg.sender === 'customer'
                              ? 'text-primary-foreground/70'
                              : 'text-muted-foreground'
                          }`}
                        >
                          {msg.timestamp}
                        </span>
                        {msg.sender === 'bot' && (msg.learningSampleId || msg.replySource === 'openai' || msg.replySource === 'faq') && (
                          <div className="mt-2 flex items-center gap-1 border-t border-border/30 pt-2">
                            <span className="text-[10px] text-muted-foreground mr-1">Rate AI reply</span>
                            <Button
                              type="button"
                              variant={msg.learningFeedback === 1 ? 'default' : 'ghost'}
                              size="icon"
                              className="h-7 w-7"
                              disabled={feedbackBusy === msg.id || msg.learningFeedback != null}
                              onClick={async () => {
                                if (!selectedChatId) return
                                setFeedbackBusy(msg.id)
                                const res = await submitMessageLearningFeedback(selectedChatId, msg.id, 1)
                                if (res.success) {
                                  mutate(['messages', selectedChatId])
                                  toast({ title: 'Thanks — helpful reply noted.' })
                                } else {
                                  toast({ title: res.message ?? 'Could not save feedback', variant: 'destructive' })
                                }
                                setFeedbackBusy(null)
                              }}
                            >
                              <ThumbsUp className="h-3.5 w-3.5" />
                            </Button>
                            <Button
                              type="button"
                              variant={msg.learningFeedback === -1 ? 'destructive' : 'ghost'}
                              size="icon"
                              className="h-7 w-7"
                              disabled={feedbackBusy === msg.id || msg.learningFeedback != null}
                              onClick={async () => {
                                if (!selectedChatId) return
                                setFeedbackBusy(msg.id)
                                const res = await submitMessageLearningFeedback(selectedChatId, msg.id, -1)
                                if (res.success) {
                                  mutate(['messages', selectedChatId])
                                  toast({ title: 'Feedback saved — sample deprioritized.' })
                                } else {
                                  toast({ title: res.message ?? 'Could not save feedback', variant: 'destructive' })
                                }
                                setFeedbackBusy(null)
                              }}
                            >
                              <ThumbsDown className="h-3.5 w-3.5" />
                            </Button>
                          </div>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </ScrollArea>

            {/* Message Input */}
            <div className="shrink-0 border-t border-border/50 p-4">
              <input
                id="chat-attachment-input"
                type="file"
                className="sr-only"
                onChange={handleAttachmentSelected}
              />
              <div className="flex items-center gap-2">
                <Button asChild variant="ghost" size="icon">
                  <label htmlFor="chat-attachment-input" aria-label="Attach a file" className="cursor-pointer">
                    <Paperclip className="h-4 w-4" />
                  </label>
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
                  disabled={(!messageInput.trim() && !selectedAttachment) || isSending}
                >
                  <Send className="h-4 w-4" />
                </Button>
              </div>
              {selectedAttachment && (
                <div className="mt-2 flex items-center justify-between rounded-md border border-border/60 bg-secondary/30 px-2 py-1 text-xs">
                  <span className="truncate text-foreground">
                    Attachment: {selectedAttachment.name}
                  </span>
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="h-6 w-6"
                    onClick={() => setSelectedAttachment(null)}
                    aria-label="Remove attachment"
                  >
                    <X className="h-3 w-3" />
                  </Button>
                </div>
              )}
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
      <div className="hidden h-full min-h-0 w-80 shrink-0 flex-col overflow-hidden rounded-xl border border-border/50 bg-card xl:flex">
        {selectedChat ? (
          <>
            <div className="shrink-0 border-b border-border/50 p-6 text-center">
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

            <ScrollArea className="min-h-0 flex-1">
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
                  <Button
                    variant="outline"
                    className="w-full justify-start border-border/50"
                    onClick={handleCreateOrder}
                  >
                    <ShoppingCart className="mr-2 h-4 w-4" />
                    Create Order
                  </Button>
                  <Button
                    variant="outline"
                    className="w-full justify-start border-border/50"
                    onClick={handleViewCustomerProfile}
                  >
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
            </ScrollArea>
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
      <FormModal
        open={createOrderOpen}
        onOpenChange={setCreateOrderOpen}
        title="Create Order"
        description={selectedChat ? `Create order for ${selectedChat.customerName}. Invoice is sent immediately on WhatsApp.` : 'Create order'}
        onSubmit={handleSubmitCreateOrder}
        submitLabel="Create & Send Invoice"
        isLoading={isCreatingOrder}
        isValid={selectedProductId.length > 0 && Number.parseInt(orderQuantity, 10) > 0 && products.length > 0}
      >
        <div className="space-y-3">
          <div>
            <p className="mb-1 text-xs text-muted-foreground">Customer</p>
            <p className="text-sm font-medium text-foreground">{selectedChat?.customerName}</p>
            <p className="text-xs text-muted-foreground">{selectedChat?.customerPhone}</p>
          </div>
          <div>
            <p className="mb-1 text-xs text-muted-foreground">Product</p>
            <Select value={selectedProductId} onValueChange={setSelectedProductId}>
              <SelectTrigger>
                <SelectValue placeholder={productsLoading ? 'Loading products...' : 'Select product'} />
              </SelectTrigger>
              <SelectContent>
                {products.map((p) => (
                  <SelectItem key={p.id} value={p.id}>
                    {p.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="grid grid-cols-2 gap-2">
            <Input
              type="number"
              min={1}
              step={1}
              placeholder="Quantity"
              value={orderQuantity}
              onChange={(e) => setOrderQuantity(e.target.value)}
            />
            <Input
              type="number"
              min={0}
              step="0.01"
              placeholder="Unit price"
              value={(() => {
                const selected = products.find((p) => p.id === selectedProductId)
                return selected ? String(selected.price) : ''
              })()}
              readOnly
            />
          </div>
          <div className="rounded-md bg-secondary/40 p-3 text-sm">
            Total:{' '}
            <span className="font-semibold text-foreground">
              {(() => {
                const qty = Number.parseInt(orderQuantity, 10)
                const selected = products.find((p) => p.id === selectedProductId)
                const price = Number(selected?.price ?? 0)
                if (!Number.isFinite(qty) || !Number.isFinite(price) || qty < 1 || price < 0) return '0.00'
                return (qty * price).toFixed(2)
              })()}
            </span>
          </div>
          {!productsLoading && products.length === 0 && (
            <p className="text-xs text-destructive">
              No active products found. Add products first, then create order.
            </p>
          )}
        </div>
      </FormModal>
    </div>
  )
}
