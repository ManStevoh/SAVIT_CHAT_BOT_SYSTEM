'use client'

import { useState, useCallback } from 'react'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { StatsCard, StatsGrid } from '@/components/shared/stats-card'
import { DataTable, type Column, type Filter } from '@/components/shared/data-table'
import { StatusBadge } from '@/components/shared/status-badge'
import { FormModal, ConfirmModal } from '@/components/shared/modal'
import { InputField, TextareaField, SelectField, SwitchField, TagInputField } from '@/components/shared/form-field'
import { useFAQs } from '@/lib/api-hooks'
import { createFAQ, updateFAQ, deleteFAQ, updateSettings } from '@/lib/api-actions'
import type { FAQ } from '@/lib/mock-data'
import {
  Plus,
  Edit,
  Trash2,
  HelpCircle,
  Bot,
  MessageSquare,
} from 'lucide-react'
import { useSWRConfig } from 'swr'

interface FAQFormData {
  question: string
  answer: string
  category: string
  keywords: string[]
}

interface BotSettings {
  greeting: string
  fallback: string
  away: string
  autoReplyEnabled: boolean
  humanHandoff: boolean
  learnFromConversations: boolean
}

const initialFormData: FAQFormData = {
  question: '',
  answer: '',
  category: '',
  keywords: [],
}

const initialBotSettings: BotSettings = {
  greeting: 'Hello! Welcome to our store. How can I help you today?',
  fallback: "I'm sorry, I didn't understand that. Let me connect you with a human agent who can help.",
  away: "Thanks for your message! We're currently closed but will get back to you as soon as we open.",
  autoReplyEnabled: true,
  humanHandoff: true,
  learnFromConversations: true,
}

export default function FAQAutomationPage() {
  const { mutate } = useSWRConfig()
  const [searchQuery, setSearchQuery] = useState('')
  const [categoryFilter, setCategoryFilter] = useState('all')
  
  // Modal states
  const [isAddModalOpen, setIsAddModalOpen] = useState(false)
  const [isEditModalOpen, setIsEditModalOpen] = useState(false)
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false)
  const [selectedFAQ, setSelectedFAQ] = useState<FAQ | null>(null)
  const [formData, setFormData] = useState<FAQFormData>(initialFormData)
  const [botSettings, setBotSettings] = useState<BotSettings>(initialBotSettings)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [formErrors, setFormErrors] = useState<Record<string, string>>({})

  // API: GET /api/company/faqs (useFAQs)
  const { data: faqs, isLoading, error } = useFAQs({
    category: categoryFilter,
    search: searchQuery,
  })

  // Calculate stats from data
  const stats = {
    total: faqs?.length || 0,
    active: faqs?.filter((f) => f.isActive).length || 0,
    totalHits: faqs?.reduce((acc, f) => acc + f.usageCount, 0) || 0,
  }

  // Validate form
  const validateForm = (): boolean => {
    const errors: Record<string, string> = {}
    
    if (!formData.question.trim()) {
      errors.question = 'Question is required'
    }
    if (!formData.answer.trim()) {
      errors.answer = 'Answer is required'
    }
    if (!formData.category) {
      errors.category = 'Category is required'
    }
    if (formData.keywords.length === 0) {
      errors.keywords = 'At least one keyword is required'
    }
    
    setFormErrors(errors)
    return Object.keys(errors).length === 0
  }

  // Handle form field change
  const handleFieldChange = (field: keyof FAQFormData, value: string | string[]) => {
    setFormData((prev) => ({ ...prev, [field]: value }))
    if (formErrors[field]) {
      setFormErrors((prev) => ({ ...prev, [field]: '' }))
    }
  }

  // Handle bot settings change
  const handleSettingsChange = (field: keyof BotSettings, value: string | boolean) => {
    setBotSettings((prev) => ({ ...prev, [field]: value }))
  }

  // Handle create FAQ — api-actions.createFAQ → POST /api/company/faqs
  const handleCreateFAQ = useCallback(async () => {
    if (!validateForm()) return

    setIsSubmitting(true)
    try {
      const result = await createFAQ({
        question: formData.question,
        answer: formData.answer,
        category: formData.category,
        keywords: formData.keywords,
      })

      if (result.success) {
        mutate(['faqs', { category: categoryFilter, search: searchQuery }])
        setIsAddModalOpen(false)
        setFormData(initialFormData)
      }
    } catch (error) {
      console.error('Failed to create FAQ:', error)
    } finally {
      setIsSubmitting(false)
    }
  }, [formData, mutate, categoryFilter, searchQuery])

  // Handle edit FAQ — api-actions.updateFAQ → PUT /api/company/faqs/:faqId
  const handleEditFAQ = useCallback(async () => {
    if (!selectedFAQ || !validateForm()) return

    setIsSubmitting(true)
    try {
      const result = await updateFAQ(selectedFAQ.id, {
        question: formData.question,
        answer: formData.answer,
        category: formData.category,
        keywords: formData.keywords,
      })

      if (result.success) {
        mutate(['faqs', { category: categoryFilter, search: searchQuery }])
        setIsEditModalOpen(false)
        setSelectedFAQ(null)
        setFormData(initialFormData)
      }
    } catch (error) {
      console.error('Failed to update FAQ:', error)
    } finally {
      setIsSubmitting(false)
    }
  }, [selectedFAQ, formData, mutate, categoryFilter, searchQuery])

  // Handle delete FAQ — api-actions.deleteFAQ → DELETE /api/company/faqs/:faqId
  const handleDeleteFAQ = useCallback(async () => {
    if (!selectedFAQ) return

    setIsSubmitting(true)
    try {
      const result = await deleteFAQ(selectedFAQ.id)

      if (result.success) {
        mutate(['faqs', { category: categoryFilter, search: searchQuery }])
        setIsDeleteModalOpen(false)
        setSelectedFAQ(null)
      }
    } catch (error) {
      console.error('Failed to delete FAQ:', error)
    } finally {
      setIsSubmitting(false)
    }
  }, [selectedFAQ, mutate, categoryFilter, searchQuery])

  // Handle toggle FAQ active status — api-actions.updateFAQ (isActive) → PUT /api/company/faqs/:faqId
  const handleToggleFAQStatus = useCallback(async (faq: FAQ) => {
    try {
      const result = await updateFAQ(faq.id, { isActive: !faq.isActive })
      if (result.success) {
        mutate(['faqs', { category: categoryFilter, search: searchQuery }])
      }
    } catch (error) {
      console.error('Failed to toggle FAQ status:', error)
    }
  }, [mutate, categoryFilter, searchQuery])

  // Handle save bot settings — api-actions.updateSettings → PUT /api/company/settings
  const handleSaveSettings = useCallback(async () => {
    setIsSubmitting(true)
    try {
      const result = await updateSettings({
        aiGreeting: botSettings.greeting,
        autoReplyEnabled: botSettings.autoReplyEnabled,
      })

      if (result.success) {
        // Show success notification
      }
    } catch (error) {
      console.error('Failed to save settings:', error)
    } finally {
      setIsSubmitting(false)
    }
  }, [botSettings])

  // Open edit modal with FAQ data
  const openEditModal = (faq: FAQ) => {
    setSelectedFAQ(faq)
    setFormData({
      question: faq.question,
      answer: faq.answer,
      category: faq.category,
      keywords: faq.keywords,
    })
    setFormErrors({})
    setIsEditModalOpen(true)
  }

  // Table columns definition
  const columns: Column<FAQ>[] = [
    {
      key: 'question',
      header: 'Question',
      cell: (faq) => (
        <div className="max-w-md">
          <p className="font-medium text-foreground">{faq.question}</p>
          <p className="text-sm text-muted-foreground truncate">{faq.answer}</p>
        </div>
      ),
    },
    {
      key: 'category',
      header: 'Category',
      cell: (faq) => (
        <Badge variant="outline" className="border-border/50">
          {faq.category}
        </Badge>
      ),
    },
    {
      key: 'keywords',
      header: 'Keywords',
      cell: (faq) => (
        <div className="flex flex-wrap gap-1">
          {faq.keywords.slice(0, 3).map((keyword) => (
            <Badge key={keyword} variant="secondary" className="text-xs">
              {keyword}
            </Badge>
          ))}
          {faq.keywords.length > 3 && (
            <Badge variant="outline" className="text-xs border-border/50">
              +{faq.keywords.length - 3}
            </Badge>
          )}
        </div>
      ),
    },
    {
      key: 'usageCount',
      header: 'Hits',
      cell: (faq) => <span className="text-foreground">{faq.usageCount}</span>,
    },
    {
      key: 'status',
      header: 'Status',
      cell: (faq) => (
        <StatusBadge status={faq.isActive ? 'active' : 'inactive'} />
      ),
    },
    {
      key: 'actions',
      header: '',
      cell: (faq) => (
        <div className="flex items-center gap-2">
          <Button variant="ghost" size="icon" onClick={() => openEditModal(faq)}>
            <Edit className="h-4 w-4" />
          </Button>
          <Button
            variant="ghost"
            size="icon"
            onClick={() => {
              setSelectedFAQ(faq)
              setIsDeleteModalOpen(true)
            }}
          >
            <Trash2 className="h-4 w-4" />
          </Button>
        </div>
      ),
    },
  ]

  // Filter options
  const filters: Filter[] = [
    {
      key: 'category',
      label: 'Category',
      options: [
        { value: 'all', label: 'All Categories' },
        { value: 'Shipping', label: 'Shipping' },
        { value: 'Payment', label: 'Payment' },
        { value: 'Returns', label: 'Returns' },
        { value: 'Products', label: 'Products' },
        { value: 'General', label: 'General' },
      ],
    },
  ]

  // FAQ form fields
  const renderFAQForm = () => (
    <div className="space-y-4">
      <InputField
        label="Question"
        name="question"
        value={formData.question}
        onChange={(value) => handleFieldChange('question', value)}
        placeholder="Enter the question customers might ask"
        error={formErrors.question}
        required
      />

      <SelectField
        label="Category"
        name="category"
        value={formData.category}
        onChange={(value) => handleFieldChange('category', value)}
        options={[
          { value: 'Shipping', label: 'Shipping' },
          { value: 'Payment', label: 'Payment' },
          { value: 'Returns', label: 'Returns' },
          { value: 'Products', label: 'Products' },
          { value: 'General', label: 'General' },
        ]}
        placeholder="Select category"
        error={formErrors.category}
        required
      />

      <TagInputField
        label="Keywords"
        name="keywords"
        value={formData.keywords}
        onChange={(value) => handleFieldChange('keywords', value)}
        placeholder="Type keyword and press Enter"
        description="Keywords help AI match this FAQ to customer questions"
        error={formErrors.keywords}
        required
      />

      <TextareaField
        label="Answer"
        name="answer"
        value={formData.answer}
        onChange={(value) => handleFieldChange('answer', value)}
        placeholder="Enter the automated response"
        rows={4}
        error={formErrors.answer}
        required
      />
    </div>
  )

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-foreground">FAQ Automation</h1>
          <p className="text-muted-foreground">
            Configure AI responses for common questions
          </p>
        </div>
        <Button onClick={() => {
          setFormData(initialFormData)
          setFormErrors({})
          setIsAddModalOpen(true)
        }}>
          <Plus className="mr-2 h-4 w-4" />
          Add FAQ
        </Button>
      </div>

      <Tabs defaultValue="faqs" className="space-y-6">
        <TabsList>
          <TabsTrigger value="faqs">FAQ Responses</TabsTrigger>
          <TabsTrigger value="settings">Bot Settings</TabsTrigger>
        </TabsList>

        <TabsContent value="faqs" className="space-y-6">
          {/* Stats Grid - API Ready */}
          <StatsGrid columns={3}>
            <StatsCard
              title="Total FAQs"
              value={stats.total}
              icon={HelpCircle}
              isLoading={isLoading}
            />
            <StatsCard
              title="Active FAQs"
              value={stats.active}
              icon={Bot}
              isLoading={isLoading}
            />
            <StatsCard
              title="Total Hits"
              value={stats.totalHits}
              icon={MessageSquare}
              isLoading={isLoading}
              formatter={(v) => v.toLocaleString()}
            />
          </StatsGrid>

          {/* FAQs Table - API Ready */}
          <Card className="bg-card border-border/50">
            <CardHeader>
              <CardTitle className="text-base font-medium">FAQ Responses</CardTitle>
            </CardHeader>
            <CardContent>
              <DataTable
                data={faqs}
                columns={columns}
                isLoading={isLoading}
                error={error}
                searchPlaceholder="Search FAQs..."
                onSearch={setSearchQuery}
                filters={filters}
                filterValues={{ category: categoryFilter }}
                onFilterChange={(key, value) => {
                  if (key === 'category') setCategoryFilter(value)
                }}
                emptyMessage="No FAQs found"
                emptyDescription="Add FAQs to automate responses to common questions"
              />
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="settings" className="space-y-6">
          <Card className="bg-card border-border/50">
            <CardHeader>
              <CardTitle>Bot Configuration</CardTitle>
              <CardDescription>
                Configure how your AI bot responds to customers
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <TextareaField
                label="Greeting Message"
                name="greeting"
                value={botSettings.greeting}
                onChange={(value) => handleSettingsChange('greeting', value)}
                rows={3}
                description="First message sent when a customer starts a conversation"
              />

              <TextareaField
                label="Fallback Message"
                name="fallback"
                value={botSettings.fallback}
                onChange={(value) => handleSettingsChange('fallback', value)}
                rows={3}
                description="Message sent when AI cannot understand the question"
              />

              <TextareaField
                label="Away Message"
                name="away"
                value={botSettings.away}
                onChange={(value) => handleSettingsChange('away', value)}
                rows={3}
                description="Message sent outside of business hours"
              />

              <div className="space-y-4 border-t border-border/50 pt-4">
                <SwitchField
                  label="Auto-reply enabled"
                  description="AI will automatically respond to messages"
                  checked={botSettings.autoReplyEnabled}
                  onCheckedChange={(checked) =>
                    handleSettingsChange('autoReplyEnabled', checked)
                  }
                />

                <SwitchField
                  label="Human handoff"
                  description="Transfer complex queries to human agents"
                  checked={botSettings.humanHandoff}
                  onCheckedChange={(checked) =>
                    handleSettingsChange('humanHandoff', checked)
                  }
                />

                <SwitchField
                  label="Learn from conversations"
                  description="AI improves responses based on interactions"
                  checked={botSettings.learnFromConversations}
                  onCheckedChange={(checked) =>
                    handleSettingsChange('learnFromConversations', checked)
                  }
                />
              </div>

              <Button onClick={handleSaveSettings} disabled={isSubmitting}>
                {isSubmitting ? 'Saving...' : 'Save Settings'}
              </Button>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

      {/* Add FAQ Modal */}
      <FormModal
        open={isAddModalOpen}
        onOpenChange={setIsAddModalOpen}
        title="Add New FAQ"
        description="Add a new automated response"
        onSubmit={handleCreateFAQ}
        submitLabel="Add FAQ"
        isLoading={isSubmitting}
        isValid={formData.question.trim() !== '' && formData.answer.trim() !== ''}
      >
        {renderFAQForm()}
      </FormModal>

      {/* Edit FAQ Modal */}
      <FormModal
        open={isEditModalOpen}
        onOpenChange={(open) => {
          if (!open) {
            setSelectedFAQ(null)
            setFormData(initialFormData)
          }
          setIsEditModalOpen(open)
        }}
        title="Edit FAQ"
        description="Update FAQ details"
        onSubmit={handleEditFAQ}
        submitLabel="Save Changes"
        isLoading={isSubmitting}
        isValid={formData.question.trim() !== '' && formData.answer.trim() !== ''}
      >
        {renderFAQForm()}
      </FormModal>

      {/* Delete Confirmation Modal */}
      <ConfirmModal
        open={isDeleteModalOpen}
        onOpenChange={(open) => {
          if (!open) setSelectedFAQ(null)
          setIsDeleteModalOpen(open)
        }}
        title="Delete FAQ"
        description={`Are you sure you want to delete this FAQ? This action cannot be undone.`}
        confirmLabel="Delete"
        onConfirm={handleDeleteFAQ}
        isLoading={isSubmitting}
        variant="destructive"
      />
    </div>
  )
}
